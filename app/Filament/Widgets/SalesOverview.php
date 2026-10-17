<?php

namespace App\Filament\Widgets;

use App\Enums\OrderStatus;
use App\Enums\ProductType;
use App\Filament\Resources\Orders\OrderResource;
use App\Filament\Resources\Products\ProductResource;
use App\Models\Order;
use App\Models\Product;
use App\Support\Money;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Carbon;

class SalesOverview extends StatsOverviewWidget
{
    protected static ?int $sort = 1;

    protected function getStats(): array
    {
        $revenue = fn (Carbon $from) => (int) Order::query()->paid()->where('paid_at', '>=', $from)->sum('total')
            - (int) Order::query()->paid()->where('paid_at', '>=', $from)->sum('refunded_total');

        $daily = collect(range(13, 0))->map(fn (int $daysAgo) => (int) Order::query()->paid()
            ->whereDate('paid_at', now()->subDays($daysAgo)->toDateString())->sum('total') / 100)->all();

        $toPack = Order::query()->whereIn('status', [OrderStatus::Paid, OrderStatus::Preparing])->count();
        $forSale = Product::query()->where('type', ProductType::Unique)->forSale()->where('stock', '>', 0)->count();
        $soldThisMonth = Product::query()->where('type', ProductType::Unique)->where('sold_at', '>=', now()->startOfMonth())->count();

        return [
            Stat::make('Bugün', Money::format($revenue(now()->startOfDay())))
                ->description(Order::query()->paid()->where('paid_at', '>=', now()->startOfDay())->count().' sipariş')
                ->chart($daily)
                ->color('success'),
            Stat::make('Bu ay', Money::format($revenue(now()->startOfMonth())))
                ->description(Order::query()->paid()->where('paid_at', '>=', now()->startOfMonth())->count().' sipariş · iadeler düşülmüş'),
            Stat::make('Hazırlanacak', (string) $toPack)
                ->description($toPack ? 'Kargoya verilmeyi bekliyor' : 'Bekleyen sipariş yok')
                ->color($toPack ? 'warning' : 'gray')
                ->url(OrderResource::getUrl()),
            Stat::make('Satışta', $forSale.' parça')
                ->description($soldThisMonth ? 'bu ay '.$soldThisMonth.' parça satıldı' : 'bu ay henüz satış yok')
                ->color('gray')
                ->url(ProductResource::getUrl()),
        ];
    }
}
