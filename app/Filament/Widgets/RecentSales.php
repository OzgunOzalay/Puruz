<?php

namespace App\Filament\Widgets;

use App\Enums\ProductType;
use App\Models\Order;
use App\Models\Product;
use Filament\Widgets\Widget;
use Illuminate\Support\Collection;

class RecentSales extends Widget
{
    protected static ?int $sort = 4;

    // Rendered with the page: the dashboard should show what needs doing without a loading step.
    protected static bool $isLazy = false;

    protected int|string|array $columnSpan = 'full';

    protected string $view = 'filament.widgets.recent-sales';

    public static function pieces(): Collection
    {
        return Product::query()->where('type', ProductType::Unique)->whereNotNull('sold_at')->latest('sold_at')->limit(5)->get();
    }

    public static function sourceLine(): ?string
    {
        $orders = Order::query()->paid()->where('paid_at', '>=', now()->subDays(30))->get(['utm_source', 'utm_medium', 'total']);
        $sum = $orders->sum('total');
        if ($sum <= 0) {
            return null;
        }

        return 'Son 30 gün: '.$orders
            ->groupBy(fn (Order $o) => $o->utm_source ? trim($o->utm_source.' '.($o->utm_medium ?? '')) : 'doğrudan')
            ->map(fn ($group) => $group->sum('total'))
            ->sortDesc()
            ->map(fn (int $total, string $source) => '%'.round($total / $sum * 100).' '.$source)
            ->implode(', ');
    }
}
