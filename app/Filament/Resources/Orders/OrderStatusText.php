<?php

namespace App\Filament\Resources\Orders;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Support\Human;
use Illuminate\Support\HtmlString;

/** Readable order status with what happens next, e.g. "Hazırlanacak · 2 gündür". */
final class OrderStatusText
{
    /** Orders waiting longer than this to be packed turn amber. */
    public const LATE_DAYS = 2;

    /** @return array{0: string, 1: string} */
    public static function for(Order $order): array
    {
        return match ($order->status) {
            OrderStatus::Paid, OrderStatus::Preparing => self::toPack($order),
            OrderStatus::Shipped => ['success', collect(['Kargoda', $order->cargo_company?->getLabel(), $order->shipped_at ? Human::days($order->shipped_at).' gün' : null])->filter()->implode(' · ')],
            OrderStatus::Delivered => ['gray', 'Teslim edildi'],
            OrderStatus::Completed => ['gray', 'Tamamlandı'],
            OrderStatus::Confirmed => ['success', self::workshopPhrase($order)],
            OrderStatus::PendingPayment => ['gray', 'Ödeme bekleniyor · '.self::minutesLeft($order)],
            OrderStatus::PaymentFailed => ['danger', 'Ödeme başarısız'],
            OrderStatus::Refunded => ['gray', 'İade edildi'],
            OrderStatus::Cancelled => ['gray', 'İptal'],
        };
    }

    public static function html(Order $order): HtmlString
    {
        [$color, $text] = self::for($order);
        $dot = match ($color) {
            'success' => 'bg-success-500',
            'warning' => 'bg-warning-500',
            'danger' => 'bg-danger-500',
            default => 'bg-gray-400',
        };

        return new HtmlString('<span class="inline-flex items-center gap-2"><span class="size-2 shrink-0 rounded-full '.$dot.'"></span>'.e($text).'</span>');
    }

    /** @return array{0: string, 1: ?string}|null sentence and the action that does it */
    public static function nextStep(Order $order): ?array
    {
        return match (true) {
            in_array($order->status, [OrderStatus::Paid, OrderStatus::Preparing], true) && $order->requires_shipping => ['Paketleyip kargoya verin', 'ship'],
            $order->status === OrderStatus::Shipped => ['Kargoda, teslim edildiğinde işaretleyin', 'markDelivered'],
            $order->status === OrderStatus::PendingPayment => ['Ödeme bekleniyor — '.self::minutesLeft($order).'; ödeme gelmezse rezervasyon bırakılır', null],
            default => null,
        };
    }

    /** @return array{0: string, 1: string} */
    private static function toPack(Order $order): array
    {
        $days = Human::days($order->paid_at ?? $order->created_at);

        return [$days > self::LATE_DAYS ? 'warning' : 'success', 'Hazırlanacak · '.($days === 0 ? 'bugünden beri' : $days.' gündür')];
    }

    private static function workshopPhrase(Order $order): string
    {
        $session = $order->items->firstWhere('workshop_session_id')?->session;

        return $session ? 'Atölye · '.$session->starts_at->translatedFormat('j M D H:i') : 'Atölye rezervasyonu';
    }

    private static function minutesLeft(Order $order): string
    {
        if (! $order->reserved_until || $order->reserved_until->isPast()) {
            return 'süresi doldu';
        }

        return max(1, (int) ceil(now()->diffInMinutes($order->reserved_until))).' dk kaldı';
    }
}
