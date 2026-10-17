<?php

namespace App\Services\Orders;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\User;
use App\Services\Payment\PaymentGateway;
use RuntimeException;

/**
 * Refunds through iyzico. A full refund on the day of payment uses Cancel (no commission);
 * otherwise each basket item is refunded by its payment transaction id.
 */
class RefundService
{
    public function __construct(
        private readonly PaymentGateway $gateway,
        private readonly ReservationService $reservations,
        private readonly OrderStatusService $statuses,
    ) {}

    public function refundAll(Order $order, ?string $reason, bool $restock, bool $notify, ?User $user, string $ip): Order
    {
        $this->assertPaid($order);
        $order->loadMissing('items');

        $canCancel = $order->refunded_total === 0 && $order->paid_at?->isToday();

        if ($canCancel) {
            $result = $this->gateway->cancel($order, $ip);
            if ($result->successful) {
                $order->refunds()->create(['amount' => $order->total, 'type' => 'cancel', 'reason' => $reason, 'provider_reference' => $result->reference, 'user_id' => $user?->id]);
                foreach ($order->items as $item) {
                    $item->forceFill(['refunded_total' => $item->charged_total])->save();
                }
                $order->forceFill(['refunded_total' => $order->total, 'shipping_refunded' => $order->shipping_total])->save();

                return $this->finish($order, $reason, $restock, $notify, $user);
            }
        }

        $errors = [];
        foreach ($order->items as $item) {
            if ($item->refundableAmount() > 0) {
                try {
                    $this->refundItem($order, $item, $item->refundableAmount(), $reason, $user, $ip);
                } catch (RuntimeException $e) {
                    $errors[] = $item->name.': '.$e->getMessage();
                }
            }
        }

        $shippingLeft = $order->shipping_total - $order->shipping_refunded;
        if ($shippingLeft > 0) {
            try {
                $this->refundShipping($order, $shippingLeft, $reason, $user, $ip);
            } catch (RuntimeException $e) {
                $errors[] = 'Kargo: '.$e->getMessage();
            }
        }

        $order->refresh();
        if ($errors !== []) {
            throw new RuntimeException('Bazı kalemler iade edilemedi: '.implode('; ', $errors));
        }

        return $this->finish($order, $reason, $restock, $notify, $user);
    }

    /** Refunds part of one line, or of the shipping fee when $item is null. */
    public function refundPartial(Order $order, ?OrderItem $item, int $amount, ?string $reason, ?User $user, string $ip): Order
    {
        $this->assertPaid($order);

        $item
            ? $this->refundItem($order, $item, $amount, $reason, $user, $ip)
            : $this->refundShipping($order, $amount, $reason, $user, $ip);

        $order->refresh();
        if ($order->refundableTotal() === 0) {
            $this->statuses->transition($order, OrderStatus::Refunded, $reason, true, $user, force: true);
        } else {
            $order->histories()->create([
                'from_status' => $order->status,
                'to_status' => $order->status,
                'note' => 'Kısmi iade yapıldı'.($reason ? ': '.$reason : '.'),
                'user_id' => $user?->id,
            ]);
        }

        return $order;
    }

    private function refundItem(Order $order, OrderItem $item, int $amount, ?string $reason, ?User $user, string $ip): void
    {
        if ($amount <= 0 || $amount > $item->refundableAmount()) {
            throw new RuntimeException('İade tutarı bu kalem için iade edilebilir tutarı aşıyor.');
        }
        if (! $item->payment_transaction_id) {
            throw new RuntimeException('Bu kalemin iyzico işlem numarası yok.');
        }

        $result = $this->gateway->refund($order, $item->payment_transaction_id, $amount, $ip);
        if (! $result->successful) {
            throw new RuntimeException($result->errorMessage ?: 'iyzico iadeyi reddetti.');
        }

        $item->increment('refunded_total', $amount);
        $order->increment('refunded_total', $amount);
        $order->refunds()->create(['order_item_id' => $item->id, 'amount' => $amount, 'type' => 'refund', 'reason' => $reason, 'provider_reference' => $result->reference, 'user_id' => $user?->id]);
    }

    private function refundShipping(Order $order, int $amount, ?string $reason, ?User $user, string $ip): void
    {
        if ($amount <= 0 || $amount > $order->shipping_total - $order->shipping_refunded) {
            throw new RuntimeException('İade tutarı kargo ücretini aşıyor.');
        }
        if (! $order->shipping_transaction_id) {
            throw new RuntimeException('Kargo ücretinin iyzico işlem numarası yok.');
        }

        $result = $this->gateway->refund($order, $order->shipping_transaction_id, $amount, $ip);
        if (! $result->successful) {
            throw new RuntimeException($result->errorMessage ?: 'iyzico iadeyi reddetti.');
        }

        $order->increment('shipping_refunded', $amount);
        $order->increment('refunded_total', $amount);
        $order->refunds()->create(['amount' => $amount, 'type' => 'refund', 'reason' => $reason ?: 'Kargo ücreti', 'provider_reference' => $result->reference, 'user_id' => $user?->id]);
    }

    private function finish(Order $order, ?string $reason, bool $restock, bool $notify, ?User $user): Order
    {
        if ($restock) {
            $this->reservations->release($order);
        }

        return $this->statuses->transition($order->refresh(), OrderStatus::Refunded, $reason, $notify, $user, force: true);
    }

    private function assertPaid(Order $order): void
    {
        if (! $order->status->isPaid() || ! $order->payment_id) {
            throw new RuntimeException('Yalnızca ödemesi alınmış siparişler iade edilebilir.');
        }
    }
}
