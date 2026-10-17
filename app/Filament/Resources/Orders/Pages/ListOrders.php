<?php

namespace App\Filament\Resources\Orders\Pages;

use App\Enums\OrderStatus;
use App\Filament\Resources\Orders\OrderResource;
use App\Models\Order;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;

class ListOrders extends ListRecords
{
    protected static string $resource = OrderResource::class;

    public function getTabs(): array
    {
        $tab = fn (string $label, array $statuses) => Tab::make($label)
            ->modifyQueryUsing(fn (Builder $query) => $query->whereIn('status', $statuses))
            ->badge(fn () => Order::query()->whereIn('status', $statuses)->count() ?: null);

        return [
            'todo' => $tab('Hazırlanacak', [OrderStatus::Paid, OrderStatus::Preparing]),
            'shipped' => $tab('Kargoda', [OrderStatus::Shipped]),
            'workshops' => $tab('Atölye rezervasyonları', [OrderStatus::Confirmed]),
            'done' => $tab('Tamamlanan', [OrderStatus::Delivered, OrderStatus::Completed]),
            'closed' => Tab::make('İptal & iade')->modifyQueryUsing(fn (Builder $query) => $query->whereIn('status', [OrderStatus::Cancelled, OrderStatus::Refunded])),
            'unpaid' => Tab::make('Ödenmemiş')->modifyQueryUsing(fn (Builder $query) => $query->whereIn('status', [OrderStatus::PendingPayment, OrderStatus::PaymentFailed])),
            'all' => Tab::make('Tümü'),
        ];
    }

    public function getDefaultActiveTab(): string|int|null
    {
        return 'todo';
    }
}
