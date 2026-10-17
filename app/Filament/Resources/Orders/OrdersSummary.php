<?php

namespace App\Filament\Resources\Orders;

use App\Enums\OrderStatus;
use App\Filament\Support\PageSummary;
use App\Models\Order;
use App\Support\Human;
use App\Support\Money;
use Illuminate\Support\Carbon;
use Illuminate\Support\HtmlString;

final class OrdersSummary
{
    public static function stats(): array
    {
        $toPack = Order::query()->whereIn('status', [OrderStatus::Paid, OrderStatus::Preparing]);
        $oldest = (clone $toPack)->oldest('paid_at')->value('paid_at');
        $week = Order::query()->paid()->where('paid_at', '>=', now()->startOfWeek());

        return [
            'to_pack' => $toPack->count(),
            'oldest_days' => $oldest ? Human::days(Carbon::parse($oldest)) : null,
            'shipped' => Order::query()->where('status', OrderStatus::Shipped)->count(),
            'week_count' => $week->count(),
            'week_total' => (int) $week->sum('total') - (int) (clone $week)->sum('refunded_total'),
            'unpaid' => Order::query()->where('status', OrderStatus::PendingPayment)->count(),
        ];
    }

    public static function html(): HtmlString
    {
        $s = self::stats();

        return PageSummary::make([
            [$s['to_pack'] ? $s['to_pack'].' sipariş hazırlanacak' : 'Hazırlanacak sipariş yok',
                $s['oldest_days'] ? '(en eskisi '.$s['oldest_days'].' gündür bekliyor)' : null],
            [$s['shipped'] ? $s['shipped'].' kargoda' : null],
            ['bu hafta '.$s['week_count'].' sipariş', Money::format($s['week_total'])],
            [$s['unpaid'] ? $s['unpaid'].' ödeme bekleniyor' : null],
        ]);
    }
}
