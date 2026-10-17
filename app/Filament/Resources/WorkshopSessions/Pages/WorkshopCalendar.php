<?php

namespace App\Filament\Resources\WorkshopSessions\Pages;

use App\Filament\Resources\WorkshopSessions\WorkshopSessionResource;
use App\Models\WorkshopSession;
use Carbon\CarbonImmutable;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Page;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Model;

/** Month/week/day calendar of workshop sessions (FullCalendar, see resources/js/filament/workshop-calendar.js). */
class WorkshopCalendar extends Page
{
    protected static string $resource = WorkshopSessionResource::class;

    protected string $view = 'filament.workshop-calendar';

    protected static ?string $title = 'Atölye takvimi';

    /** One colour per workshop, picked by product id. */
    private const COLORS = ['#2d4ba0', '#c2410c', '#15803d', '#7e22ce', '#0e7490', '#b45309', '#be185d', '#4d7c0f'];

    private ?WorkshopSession $clickedSession = null;

    protected function getHeaderActions(): array
    {
        return [
            WorkshopSessionResource::generateAction()->after(fn () => $this->refreshCalendar()),
            $this->createSessionAction(),
            Action::make('list')
                ->label('Liste')
                ->icon('heroicon-o-list-bullet')
                ->color('gray')
                ->url(WorkshopSessionResource::getUrl('list')),
        ];
    }

    /**
     * Sessions overlapping the range FullCalendar is showing. Dates travel as wall-clock time in the
     * shop's timezone (the calendar runs in "UTC" mode so the admin's own timezone never shifts them).
     *
     * @return list<array<string, mixed>>
     */
    public function fetchEvents(string $start, string $end): array
    {
        return WorkshopSession::query()
            ->with('product')
            ->withExists('orderItems as has_bookings')
            ->where('starts_at', '<', $this->parseDate($end))
            ->where('ends_at', '>', $this->parseDate($start))
            ->get()
            ->map(fn (WorkshopSession $session) => [
                'id' => $session->id,
                'title' => $session->product->name.' · '.$session->seats_reserved.'/'.$session->capacity,
                'start' => $session->starts_at->format('Y-m-d\TH:i:s'),
                'end' => $session->ends_at->format('Y-m-d\TH:i:s'),
                'color' => $session->ends_at->isPast() ? '#a8a29e' : self::COLORS[$session->product_id % count(self::COLORS)],
                'editable' => ! $session->has_bookings,
                'classNames' => $session->is_visible ? [] : ['puruz-session-hidden'],
            ])
            ->all();
    }

    /** Called after a session is dragged or resized. Returning false makes the calendar undo the move. */
    public function moveSession(int $id, string $start, string $end): bool
    {
        $session = WorkshopSession::query()->find($id);
        $startsAt = $this->parseDate($start);
        $endsAt = $this->parseDate($end);

        if (! $session) {
            return false;
        }

        if ($session->orderItems()->exists()) {
            Notification::make()->danger()->title('Rezervasyonu olan seans taşınamaz')
                ->body('Tarihi değiştirmek için seansa tıklayıp "Düzenle"yi kullanın ve katılımcılara haber verin.')->send();

            return false;
        }

        if ($endsAt <= $startsAt) {
            Notification::make()->danger()->title('Bitiş, başlangıçtan sonra olmalı')->send();

            return false;
        }

        $session->update(['starts_at' => $startsAt, 'ends_at' => $endsAt]);
        Notification::make()->success()->title('Seans taşındı')->body($session->displayLabel())->send();

        return true;
    }

    /** Opened by clicking an empty day (month view) or selecting hours (week/day view). */
    public function createSessionAction(): CreateAction
    {
        return CreateAction::make('createSession')
            ->label('Tek seans ekle')
            ->model(WorkshopSession::class)
            ->schema(fn (Schema $schema) => WorkshopSessionResource::form($schema))
            ->fillForm(fn (array $arguments) => $this->prefill($arguments))
            ->after(fn () => $this->refreshCalendar());
    }

    /** Opened by clicking a session: details and attendees, with edit, export and delete. */
    public function sessionAction(): Action
    {
        return Action::make('session')
            ->modalHeading(fn (WorkshopSession $record) => $record->product->name)
            ->modalDescription(fn (WorkshopSession $record) => $record->displayLabel())
            ->modalContent(fn (WorkshopSession $record) => view('filament.session-summary', [
                'session' => $record,
                'items' => WorkshopSessionResource::attendees($record),
            ]))
            ->modalSubmitAction(false)
            ->modalCancelActionLabel('Kapat')
            ->extraModalFooterActions([
                EditAction::make('edit')
                    ->schema(fn (Schema $schema) => WorkshopSessionResource::form($schema))
                    ->cancelParentActions()
                    ->after(fn () => $this->refreshCalendar()),
                Action::make('export')
                    ->label('Katılımcı listesi (CSV)')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->color('gray')
                    ->action(fn (WorkshopSession $record) => WorkshopSessionResource::exportAttendees($record)),
                DeleteAction::make('delete')
                    ->disabled(fn (WorkshopSession $record) => $record->orderItems()->exists())
                    ->tooltip(fn (WorkshopSession $record) => $record->orderItems()->exists() ? 'Rezervasyonu olan seans silinemez; gizleyebilirsiniz.' : null)
                    ->cancelParentActions()
                    ->after(fn () => $this->refreshCalendar()),
            ]);
    }

    /** The session actions all act on the session that was clicked to open them. */
    public function getDefaultActionRecord(Action $action): ?Model
    {
        while ($action->getParentAction()) {
            $action = $action->getParentAction();
        }

        $id = $action->getArguments()['session'] ?? null;

        if ($id && $this->clickedSession?->getKey() !== (int) $id) {
            $this->clickedSession = WorkshopSession::query()->with('product')->find($id);
        }

        return $id ? $this->clickedSession : null;
    }

    /** @return array<string, mixed> */
    private function prefill(array $arguments): array
    {
        $defaults = ['capacity' => 8, 'is_visible' => true];

        if (blank($arguments['start'] ?? null)) {
            return $defaults;
        }

        $start = $this->parseDate($arguments['start']);

        if ($arguments['allDay'] ?? false) {
            [$start, $end] = [$start->setTime(11, 0), $start->setTime(14, 0)];
        } else {
            $end = filled($arguments['end'] ?? null) ? $this->parseDate($arguments['end']) : $start;

            // A plain click selects a single 30-minute slot; treat it as "starts here".
            if ($start->diffInMinutes($end) <= 30) {
                $end = $start->addHours(3);
            }
        }

        return $defaults + [
            'starts_at' => $start->format('Y-m-d H:i'),
            'ends_at' => $end->format('Y-m-d H:i'),
        ];
    }

    /** "2026-11-04T19:00:00Z" from the calendar is wall-clock time, not UTC; drop the offset. */
    private function parseDate(string $value): CarbonImmutable
    {
        return CarbonImmutable::parse(substr($value, 0, 19), config('app.timezone'));
    }

    private function refreshCalendar(): void
    {
        $this->dispatch('workshop-calendar-refresh');
    }
}
