<?php

namespace App\Filament\Resources\WorkshopSessions;

use App\Enums\ProductType;
use App\Filament\Resources\WorkshopSessions\Pages\ManageWorkshopSessions;
use App\Filament\Support\MoneyInput;
use App\Filament\Support\NavigationGroup;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\WorkshopSession;
use App\Support\Money;
use BackedEnum;
use Carbon\CarbonImmutable;
use Carbon\CarbonPeriod;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\TimePicker;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\StreamedResponse;
use UnitEnum;

class WorkshopSessionResource extends Resource
{
    protected static ?string $model = WorkshopSession::class;

    protected static ?string $slug = 'atolye-takvimi';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCalendarDays;

    protected static string|UnitEnum|null $navigationGroup = NavigationGroup::Catalog;

    protected static ?int $navigationSort = 3;

    protected static ?string $modelLabel = 'atölye seansı';

    protected static ?string $pluralModelLabel = 'atölye takvimi';

    protected static ?string $navigationLabel = 'Atölye takvimi';

    public static function form(Schema $schema, bool $withProduct = true): Schema
    {
        return $schema->columns(2)->components([
            Select::make('product_id')
                ->label('Atölye')
                ->options(fn () => self::workshopOptions())
                ->required()
                ->columnSpanFull()
                ->visible($withProduct),
            TextInput::make('label')->label('Grup adı')->placeholder('ör. Kasım · Salı grubu')->maxLength(120),
            TextInput::make('schedule_note')->label('Program notu')->placeholder('ör. 4 hafta, her salı')->maxLength(190),
            DateTimePicker::make('starts_at')->label('Başlangıç')->native(false)->seconds(false)->required(),
            DateTimePicker::make('ends_at')->label('Bitiş')->native(false)->seconds(false)->required()->after('starts_at'),
            TextInput::make('capacity')->label('Kontenjan')->numeric()->integer()->minValue(1)->default(8)->required(),
            MoneyInput::make('price')->label('Bu seansa özel fiyat')->helperText('Boşsa atölye fiyatı geçerli.'),
            Toggle::make('is_visible')->label('Sitede göster')->default(true),
        ]);
    }

    public static function table(Table $table): Table
    {
        return self::sessionsTable($table);
    }

    public static function sessionsTable(Table $table, bool $withProduct = true): Table
    {
        return $table
            ->defaultSort('starts_at')
            ->modifyQueryUsing(fn (Builder $query) => $query->with('product'))
            ->columns([
                TextColumn::make('product.name')->label('Atölye')->searchable()->visible($withProduct),
                TextColumn::make('starts_at')
                    ->label('Tarih')
                    ->sortable()
                    ->formatStateUsing(fn (WorkshopSession $record) => $record->starts_at->translatedFormat('j F Y, l'))
                    ->description(fn (WorkshopSession $record) => $record->starts_at->format('H:i').'–'.$record->ends_at->format('H:i')),
                TextColumn::make('label')->label('Grup')->description(fn (WorkshopSession $record) => $record->schedule_note)->placeholder('—'),
                TextColumn::make('seats_reserved')
                    ->label('Doluluk')
                    ->formatStateUsing(fn (WorkshopSession $record) => $record->seats_reserved.' / '.$record->capacity)
                    ->badge()
                    ->color(fn (WorkshopSession $record) => match (true) {
                        $record->seatsLeft() === 0 => 'danger',
                        $record->seatsLeft() <= 2 => 'warning',
                        default => 'success',
                    }),
                TextColumn::make('price')->label('Fiyat')
                    ->state(fn (WorkshopSession $record) => $record->effectivePrice())
                    ->formatStateUsing(fn (int $state) => Money::format($state)),
                ToggleColumn::make('is_visible')->label('Görünür'),
            ])
            ->filters([
                TernaryFilter::make('upcoming')
                    ->label('Zaman')
                    ->placeholder('Tümü')
                    ->trueLabel('Yaklaşanlar')
                    ->falseLabel('Geçmiş')
                    ->default(true)
                    ->queries(
                        true: fn (Builder $query) => $query->where('starts_at', '>=', now()->startOfDay()),
                        false: fn (Builder $query) => $query->where('starts_at', '<', now()->startOfDay()),
                        blank: fn (Builder $query) => $query,
                    ),
            ])
            ->headerActions([
                self::generateAction($withProduct),
                CreateAction::make()->label('Tek seans ekle'),
            ])
            ->recordActions([
                ActionGroup::make([
                    Action::make('attendees')
                        ->label('Katılımcılar')
                        ->icon('heroicon-o-users')
                        ->modalHeading(fn (WorkshopSession $record) => 'Katılımcılar · '.$record->displayLabel())
                        ->modalContent(fn (WorkshopSession $record) => view('filament.session-attendees', ['items' => self::attendees($record)]))
                        ->modalSubmitAction(false)
                        ->modalCancelActionLabel('Kapat'),
                    Action::make('export')
                        ->label('Katılımcı listesi (CSV)')
                        ->icon('heroicon-o-arrow-down-tray')
                        ->action(fn (WorkshopSession $record) => self::exportAttendees($record)),
                    EditAction::make(),
                    DeleteAction::make()
                        ->disabled(fn (WorkshopSession $record) => $record->orderItems()->exists())
                        ->tooltip('Rezervasyonu olan seans silinemez; gizleyebilirsiniz.'),
                ]),
            ])
            ->toolbarActions([BulkActionGroup::make([
                DeleteBulkAction::make()->action(function (Collection $records) {
                    $deletable = $records->filter(fn (WorkshopSession $s) => ! $s->orderItems()->exists());
                    $deletable->each->delete();
                    Notification::make()->title($deletable->count().' seans silindi')->body($records->count() > $deletable->count() ? 'Rezervasyonu olan seanslar silinmedi.' : null)->success()->send();
                }),
            ])]);
    }

    /** Creates many sessions at once, e.g. every Tuesday and Thursday 11:00–14:00 and 19:00–22:00 in a month. */
    private static function generateAction(bool $withProduct): Action
    {
        return Action::make('generate')
            ->label('Toplu seans oluştur')
            ->icon('heroicon-o-sparkles')
            ->modalWidth('2xl')
            ->schema([
                Select::make('product_id')->label('Atölye')->options(fn () => self::workshopOptions())->required()->visible($withProduct),
                Grid::make(2)->schema([
                    DatePicker::make('from')->label('İlk tarih')->native(false)->required()->default(now()->addMonthNoOverflow()->startOfMonth()),
                    DatePicker::make('until')->label('Son tarih')->native(false)->required()->afterOrEqual('from')->default(now()->addMonthNoOverflow()->startOfMonth()->addDays(6)),
                ]),
                CheckboxList::make('weekdays')
                    ->label('Günler')
                    ->options([1 => 'Pazartesi', 2 => 'Salı', 3 => 'Çarşamba', 4 => 'Perşembe', 5 => 'Cuma', 6 => 'Cumartesi', 0 => 'Pazar'])
                    ->columns(4)
                    ->required(),
                Repeater::make('slots')
                    ->label('Saatler')
                    ->schema([
                        TimePicker::make('start')->label('Başlangıç')->seconds(false)->required(),
                        TimePicker::make('end')->label('Bitiş')->seconds(false)->required(),
                    ])
                    ->columns(2)
                    ->default([['start' => '11:00', 'end' => '14:00'], ['start' => '19:00', 'end' => '22:00']])
                    ->minItems(1)
                    ->addActionLabel('Saat ekle'),
                Grid::make(2)->schema([
                    TextInput::make('capacity')->label('Kontenjan')->numeric()->integer()->minValue(1)->default(8)->required(),
                    MoneyInput::make('price')->label('Özel fiyat (isteğe bağlı)'),
                    TextInput::make('label')->label('Grup adı')->placeholder('ör. Kasım grubu')->helperText('Gün adı otomatik eklenir.'),
                    TextInput::make('schedule_note')->label('Program notu')->placeholder('ör. 4 hafta'),
                ]),
            ])
            ->action(function (array $data, $livewire) {
                $productId = $livewire instanceof RelationManager ? $livewire->getOwnerRecord()->getKey() : $data['product_id'];
                $weekdays = array_map('intval', $data['weekdays']);
                $created = 0;

                DB::transaction(function () use ($data, $productId, $weekdays, &$created) {
                    foreach (CarbonPeriod::create($data['from'], $data['until']) as $day) {
                        $day = CarbonImmutable::instance($day);
                        if (! in_array($day->dayOfWeek, $weekdays, true)) {
                            continue;
                        }

                        foreach ($data['slots'] as $slot) {
                            WorkshopSession::query()->create([
                                'product_id' => $productId,
                                'label' => trim(($data['label'] ?? '').' · '.$day->translatedFormat('l'), ' ·'),
                                'schedule_note' => $data['schedule_note'] ?? null,
                                'starts_at' => $day->setTimeFromTimeString($slot['start']),
                                'ends_at' => $day->setTimeFromTimeString($slot['end']),
                                'capacity' => (int) $data['capacity'],
                                'price' => $data['price'] ?? null,
                            ]);
                            $created++;
                        }
                    }
                });

                Notification::make()->success()->title($created.' seans oluşturuldu')->send();
            });
    }

    /** @return \Illuminate\Support\Collection<int, OrderItem> */
    public static function attendees(WorkshopSession $session): \Illuminate\Support\Collection
    {
        return OrderItem::query()
            ->where('workshop_session_id', $session->id)
            ->whereHas('order', fn (Builder $query) => $query->paid())
            ->with('order')
            ->get();
    }

    private static function exportAttendees(WorkshopSession $session): StreamedResponse
    {
        $items = self::attendees($session);
        $filename = 'katilimcilar-'.$session->starts_at->format('Y-m-d-Hi').'.csv';

        return response()->streamDownload(function () use ($items) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF"); // BOM so Excel opens Turkish characters correctly
            fputcsv($out, ['Katılımcı', 'Sipariş', 'Müşteri', 'Telefon', 'E-posta', 'Kişi sayısı'], ';');
            foreach ($items as $item) {
                fputcsv($out, [
                    implode(', ', $item->participants ?? []),
                    $item->order->number,
                    $item->order->customer_name,
                    $item->order->customer_phone,
                    $item->order->customer_email,
                    $item->quantity,
                ], ';');
            }
            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    private static function workshopOptions(): array
    {
        return Product::query()->where('type', ProductType::Workshop)->orderBy('name')->pluck('name', 'id')->all();
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageWorkshopSessions::route('/'),
        ];
    }
}
