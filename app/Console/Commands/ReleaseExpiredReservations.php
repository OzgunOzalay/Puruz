<?php

namespace App\Console\Commands;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Services\Orders\ReservationService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('shop:release-reservations')]
#[Description('Unpaid orders past their reservation window give their stock and seats back')]
class ReleaseExpiredReservations extends Command
{
    public function handle(ReservationService $reservations): int
    {
        $orders = Order::query()
            ->where('status', OrderStatus::PendingPayment)
            ->where('reservation_released', false)
            ->where('reserved_until', '<', now())
            ->get();

        foreach ($orders as $order) {
            $reservations->release($order);
            $order->forceFill(['status' => OrderStatus::PaymentFailed, 'payment_error' => 'Ödeme süresi doldu.'])->save();
            $order->histories()->create([
                'from_status' => OrderStatus::PendingPayment,
                'to_status' => OrderStatus::PaymentFailed,
                'note' => 'Ödeme süresi doldu, rezervasyon serbest bırakıldı.',
            ]);
        }

        $this->info($orders->count().' rezervasyon serbest bırakıldı.');

        return self::SUCCESS;
    }
}
