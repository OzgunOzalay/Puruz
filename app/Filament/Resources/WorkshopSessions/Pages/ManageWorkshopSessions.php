<?php

namespace App\Filament\Resources\WorkshopSessions\Pages;

use App\Filament\Resources\WorkshopSessions\WorkshopSessionResource;
use App\Filament\Support\PageSummary;
use App\Models\WorkshopSession;
use Filament\Actions\Action;
use Filament\Resources\Pages\ManageRecords;
use Illuminate\Contracts\Support\Htmlable;

class ManageWorkshopSessions extends ManageRecords
{
    protected static string $resource = WorkshopSessionResource::class;

    public function getSubheading(): string|Htmlable|null
    {
        $upcoming = WorkshopSession::query()->where('starts_at', '>', now())->orderBy('starts_at')->get();

        return PageSummary::make([[
            $upcoming->count().' seans · '.$upcoming->sum(fn (WorkshopSession $s) => $s->seatsLeft()).' boş yer',
            $upcoming->first() ? '· sıradaki '.$upcoming->first()->starts_at->translatedFormat('j M D') : null,
        ]]);
    }

    protected static ?string $title = 'Atölye seansları (liste)';

    protected function getHeaderActions(): array
    {
        return [
            Action::make('calendar')
                ->label('Takvim')
                ->icon('heroicon-o-calendar-days')
                ->color('gray')
                ->url(WorkshopSessionResource::getUrl()),
        ];
    }
}
