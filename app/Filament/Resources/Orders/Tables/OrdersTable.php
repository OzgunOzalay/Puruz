<?php

namespace App\Filament\Resources\Orders\Tables;

use App\Enums\OrderStatus;
use App\Filament\Resources\Orders\OrderActions;
use App\Filament\Resources\Orders\OrderResource;
use App\Models\Order;
use App\Support\Money;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

class OrdersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->modifyQueryUsing(fn (Builder $query) => $query->with('items'))
            ->recordUrl(fn (Order $record) => OrderResource::getUrl('view', ['record' => $record]))
            ->columns([
                TextColumn::make('number')
                    ->label('Sipariş')
                    ->searchable(['number', 'customer_name', 'customer_email', 'customer_phone'])
                    ->weight('medium')
                    ->description(fn (Order $record) => $record->created_at->translatedFormat('j M Y H:i')),
                TextColumn::make('customer_name')
                    ->label('Müşteri')
                    ->description(fn (Order $record) => $record->requires_shipping ? $record->shipping_district.' / '.$record->shipping_city : 'Atölye'),
                TextColumn::make('items_summary')
                    ->label('Ürünler')
                    ->state(fn (Order $record) => $record->items->map(fn ($i) => $i->quantity.'× '.$i->name)->implode(', '))
                    ->limit(40)
                    ->tooltip(fn (Order $record) => $record->items->map(fn ($i) => $i->quantity.'× '.$i->displayName())->implode("\n"))
                    ->toggleable(),
                TextColumn::make('total')
                    ->label('Tutar')
                    ->sortable()
                    ->formatStateUsing(fn (int $state) => Money::format($state))
                    ->description(fn (Order $record) => $record->refunded_total ? 'iade: '.Money::format($record->refunded_total) : null),
                TextColumn::make('status')->label('Durum')->badge()->sortable(),
                TextColumn::make('cargo_tracking_number')
                    ->label('Kargo')
                    ->description(fn (Order $record) => $record->cargo_company?->getLabel())
                    ->placeholder('—')
                    ->toggleable(),
                TextColumn::make('utm_source')
                    ->label('Kaynak')
                    ->formatStateUsing(fn (Order $record) => trim($record->utm_source.' '.($record->utm_medium ? '/ '.$record->utm_medium : '')))
                    ->placeholder('doğrudan')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('created_at')
                    ->label('Tarih')
                    ->dateTime('d.m.Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('status')->label('Durum')->options(OrderStatus::class)->multiple(),
                SelectFilter::make('utm_source')->label('Kaynak')
                    ->options(fn () => Order::query()->whereNotNull('utm_source')->distinct()->pluck('utm_source', 'utm_source')->all()),
                Filter::make('created_at')
                    ->schema([
                        DatePicker::make('from')->label('Başlangıç')->native(false),
                        DatePicker::make('until')->label('Bitiş')->native(false),
                    ])
                    ->query(fn (Builder $query, array $data) => $query
                        ->when($data['from'] ?? null, fn (Builder $q, $date) => $q->whereDate('created_at', '>=', $date))
                        ->when($data['until'] ?? null, fn (Builder $q, $date) => $q->whereDate('created_at', '<=', $date)))
                    ->indicateUsing(fn (array $data) => array_filter([
                        isset($data['from']) ? 'Başlangıç: '.$data['from'] : null,
                        isset($data['until']) ? 'Bitiş: '.$data['until'] : null,
                    ])),
            ])
            ->recordActions([
                OrderActions::ship()->iconButton()->tooltip('Kargoya ver'),
                ViewAction::make()->iconButton(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    BulkAction::make('preparing')
                        ->label('Hazırlanıyor olarak işaretle')
                        ->icon('heroicon-o-archive-box')
                        ->requiresConfirmation()
                        ->modalDescription('Seçilen ve ödemesi alınmış siparişler “Hazırlanıyor” durumuna geçer, müşterilere e-posta gider.')
                        ->action(fn (Collection $records) => OrderActions::bulkPreparing($records)),
                    BulkAction::make('export')
                        ->label('CSV olarak indir')
                        ->icon('heroicon-o-arrow-down-tray')
                        ->action(fn (Collection $records) => OrderActions::exportCsv($records)),
                ]),
            ]);
    }
}
