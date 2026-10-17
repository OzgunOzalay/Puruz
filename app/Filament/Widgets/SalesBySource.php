<?php

namespace App\Filament\Widgets;

use App\Models\Order;
use Filament\Widgets\ChartWidget;

/** Which Instagram placements bring paying customers (last 30 days, from UTM tags). */
class SalesBySource extends ChartWidget
{
    protected static ?int $sort = 4;

    protected ?string $heading = 'Satış kaynağı (son 30 gün)';

    protected ?string $maxHeight = '260px';

    protected function getData(): array
    {
        $rows = Order::query()->paid()
            ->where('paid_at', '>=', now()->subDays(30))
            ->get(['utm_source', 'utm_medium', 'total'])
            ->groupBy(fn (Order $o) => $o->utm_source ? trim($o->utm_source.' '.($o->utm_medium ?? '')) : 'doğrudan')
            ->map(fn ($orders) => round($orders->sum('total') / 100))
            ->sortDesc();

        return [
            'datasets' => [[
                'label' => 'Ciro (₺)',
                'data' => $rows->values()->all(),
                'backgroundColor' => ['#2d4ba0', '#d9402b', '#c9b79c', '#3f6b4f', '#141414', '#8a877e'],
            ]],
            'labels' => $rows->keys()->all(),
        ];
    }

    protected function getType(): string
    {
        return 'doughnut';
    }
}
