<?php

namespace App\Filament\Widgets;

use App\Enums\OrderStatus;
use App\Filament\Resources\Orders\OrderResource;
use App\Models\Order;
use App\Support\Money;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;

class LatestOrders extends TableWidget
{
    protected static ?int $sort = 2;

    protected int|string|array $columnSpan = 'full';

    protected static ?string $heading = 'Hazırlanacak siparişler';

    public function table(Table $table): Table
    {
        return $table
            ->query(Order::query()->whereIn('status', [OrderStatus::Paid, OrderStatus::Preparing])->with('items')->oldest('paid_at'))
            ->paginated([5, 10])
            ->recordUrl(fn (Order $record) => OrderResource::getUrl('view', ['record' => $record]))
            ->emptyStateHeading('Hazırlanacak sipariş yok')
            ->columns([
                TextColumn::make('number')->label('Sipariş')->weight('medium'),
                TextColumn::make('paid_at')->label('Ödeme')->since(),
                TextColumn::make('customer_name')->label('Müşteri'),
                TextColumn::make('items')->label('Ürünler')
                    ->state(fn (Order $record) => $record->items->map(fn ($i) => $i->quantity.'× '.$i->name)->implode(', '))
                    ->limit(50),
                TextColumn::make('total')->label('Tutar')->formatStateUsing(fn (int $state) => Money::format($state)),
                TextColumn::make('status')->label('Durum')->badge(),
            ]);
    }
}
