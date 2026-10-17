<?php

namespace App\Services\Payment;

use App\Enums\OrderStatus;
use App\Mail\AdminAlert;
use App\Mail\AdminNewOrder;
use App\Mail\OrderConfirmed;
use App\Mail\OrderStatusChanged;
use App\Models\Order;
use App\Services\Orders\ReservationService;
use App\Support\Settings;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Settles an order from an iyzico checkout token. Called from both the browser callback and
 * the webhook, so it must be safe to run more than once for the same token.
 */
class PaymentService
{
    public function __construct(
        private readonly PaymentGateway $gateway,
        private readonly ReservationService $reservations,
    ) {}

    public function start(Order $order, string $ip): string
    {
        $result = $this->gateway->initialize($order, route('payment.callback'), $ip);

        $order->forceFill(['payment_token' => $result->token, 'payment_error' => null])->save();

        return $result->paymentPageUrl;
    }

    public function complete(string $token, string $ip = '127.0.0.1'): ?Order
    {
        $outcome = DB::transaction(function () use ($token) {
            $order = Order::query()->where('payment_token', $token)->lockForUpdate()->first();
            if (! $order) {
                return null;
            }

            if ($order->status !== OrderStatus::PendingPayment && $order->status !== OrderStatus::PaymentFailed) {
                return ['order' => $order, 'event' => null];
            }

            $result = $this->gateway->retrieve($token);
            $problem = $this->verify($order, $result);

            if (! $result->successful) {
                $order->forceFill([
                    'status' => OrderStatus::PaymentFailed,
                    'payment_error' => mb_substr((string) $result->errorMessage, 0, 250),
                ])->save();
                $order->histories()->create([
                    'from_status' => OrderStatus::PendingPayment,
                    'to_status' => OrderStatus::PaymentFailed,
                    'note' => $result->errorMessage,
                ]);

                return ['order' => $order, 'event' => 'failed'];
            }

            if ($problem) {
                Log::critical('iyzico ödeme doğrulaması başarısız', ['order' => $order->number, 'problem' => $problem]);
                $order->forceFill(['payment_error' => $problem, 'payment_response' => $result->raw])->save();

                return ['order' => $order, 'event' => 'suspicious', 'problem' => $problem];
            }

            $order->forceFill([
                'status' => OrderStatus::Paid,
                'payment_id' => $result->paymentId,
                'installment' => $result->installment,
                'card_family' => $result->cardFamily,
                'card_association' => $result->cardAssociation,
                'card_last_four' => $result->lastFour,
                'payment_response' => $result->raw,
                'payment_error' => null,
                'paid_at' => now(),
                'shipping_transaction_id' => $result->transactionIds[BasketBuilder::SHIPPING_ID] ?? null,
            ])->save();

            foreach ($order->items as $item) {
                $item->forceFill(['payment_transaction_id' => $result->transactionIds[BasketBuilder::itemId($item->id)] ?? null])->save();
            }

            $order->histories()->create([
                'from_status' => OrderStatus::PendingPayment,
                'to_status' => OrderStatus::Paid,
                'note' => 'Ödeme kredi/banka kartı ile alındı.',
            ]);

            return ['order' => $order, 'event' => 'paid'];
        });

        if ($outcome === null) {
            return null;
        }

        $order = $outcome['order']->fresh(['items']);

        match ($outcome['event']) {
            'failed' => $this->reservations->release($order),
            'paid' => $this->afterPaid($order, $ip),
            'suspicious' => $this->alertAdmin($order, 'Ödeme iyzico tarafında başarılı görünüyor ancak doğrulama başarısız: '.$outcome['problem'].'. iyzico panelinden kontrol edin.'),
            default => null,
        };

        return $order->fresh();
    }

    private function verify(Order $order, PaymentResult $result): ?string
    {
        if (! $result->successful) {
            return null;
        }

        return match (true) {
            ! $result->signatureValid => 'imza doğrulanamadı',
            $result->conversationId !== $order->number => 'sipariş numarası eşleşmiyor',
            $result->basketId !== null && $result->basketId !== $order->number => 'sepet numarası eşleşmiyor',
            $result->currency !== null && $result->currency !== 'TRY' => 'para birimi TRY değil',
            $result->price !== $order->total => 'tutar eşleşmiyor',
            $result->paidPrice === null || $result->paidPrice < $order->total => 'ödenen tutar eksik',
            default => null,
        };
    }

    private function afterPaid(Order $order, string $ip): void
    {
        // The customer paid after the reservation had expired: take stock again or void the payment.
        if ($order->reservation_released && ! $this->reservations->reacquire($order)) {
            $cancel = $this->gateway->cancel($order, $ip);
            $order->forceFill(['status' => OrderStatus::Cancelled])->save();
            $order->histories()->create([
                'from_status' => OrderStatus::Paid,
                'to_status' => OrderStatus::Cancelled,
                'note' => 'Ödeme süresi aşıldığı için ürün/kontenjan başka bir siparişe geçti; ödeme iptal edildi.',
                'customer_notified' => true,
            ]);

            if ($cancel->successful) {
                $order->forceFill(['refunded_total' => $order->total])->save();
                $order->refunds()->create(['amount' => $order->total, 'type' => 'cancel', 'reason' => 'Rezervasyon süresi doldu', 'provider_reference' => $cancel->reference]);
            } else {
                $this->alertAdmin($order, 'Süresi dolan rezervasyon için ödeme otomatik iptal edilemedi: '.$cancel->errorMessage);
            }

            Mail::to($order->customer_email)->send(new OrderStatusChanged($order, OrderStatus::Cancelled, 'Ödeme süresi aşıldığı için siparişinizi tamamlayamadık. Ücret kartınıza iade edildi.'));

            return;
        }

        $this->reservations->markSold($order);

        // Workshop-only orders need no preparation, the booking is confirmed right away.
        if (! $order->requires_shipping) {
            $order->forceFill(['status' => OrderStatus::Confirmed])->save();
            $order->histories()->create([
                'from_status' => OrderStatus::Paid,
                'to_status' => OrderStatus::Confirmed,
                'note' => 'Rezervasyonunuz onaylandı.',
                'customer_notified' => true,
            ]);
        }

        Mail::to($order->customer_email)->send(new OrderConfirmed($order));

        if ($admin = Settings::get('admin_notification_email')) {
            Mail::to($admin)->send(new AdminNewOrder($order));
        }
    }

    private function alertAdmin(Order $order, string $message): void
    {
        if ($admin = Settings::get('admin_notification_email')) {
            Mail::to($admin)->send(new AdminAlert($order, $message));
        }
    }
}
