<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\Orders\OrderResource;
use App\Filament\Resources\Orders\OrdersSummary;
use App\Filament\Resources\Orders\OrderStatusText;
use App\Filament\Resources\Products\ProductResource;
use App\Filament\Resources\Products\ProductsSummary;
use App\Filament\Resources\SimilarRequests\SimilarRequestResource;
use App\Filament\Resources\SimilarRequests\SimilarRequestsSummary;
use App\Models\Order;
use App\Support\Money;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class SalesOverview extends StatsOverviewWidget
{
    protected static ?int $sort = 1;

    protected function getStats(): array
    {
        $orders = OrdersSummary::stats();
        $requests = SimilarRequestsSummary::stats();
        $pieces = ProductsSummary::stats();

        $lastWeek = (int) Order::query()->paid()->whereBetween('paid_at', [now()->subWeek()->startOfWeek(), now()->subWeek()])->sum('total');
        $change = $lastWeek > 0 ? (int) round(($orders['week_total'] - $lastWeek) / $lastWeek * 100) : null;
        $daily = collect(range(13, 0))->map(fn (int $daysAgo) => (int) Order::query()->paid()
            ->whereDate('paid_at', now()->subDays($daysAgo)->toDateString())->sum('total') / 100)->all();

        return [
            Stat::make('Bu hafta', Money::format($orders['week_total']))
                ->description($orders['week_count'].' sipariş'.($change !== null ? ', geçen haftadan %'.abs($change).($change >= 0 ? ' fazla' : ' az') : ''))
                ->chart($daily)
                ->color('gray'),
            Stat::make('Hazırlanacak', (string) $orders['to_pack'])
                ->description($orders['oldest_days'] !== null ? 'en eskisi '.$orders['oldest_days'].' gündür bekliyor' : 'bekleyen sipariş yok')
                ->color($orders['oldest_days'] > OrderStatusText::LATE_DAYS ? 'warning' : 'gray')
                ->url(OrderResource::getUrl()),
            Stat::make('Talepler', $requests['new'].' yeni')
                ->description($requests['open'].' yapılıyor')
                ->color('gray')
                ->url(SimilarRequestResource::getUrl()),
            Stat::make('Satışta', $pieces['available_count'].' parça')
                ->description($pieces['sold_month_count'] ? 'bu ay '.$pieces['sold_month_count'].' satıldı' : 'bu ay henüz satış yok')
                ->color('gray')
                ->url(ProductResource::getUrl()),
        ];
    }
}
