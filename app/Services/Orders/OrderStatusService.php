<?php

namespace App\Services\Orders;

use App\Enums\CargoCompany;
use App\Enums\OrderStatus;
use App\Mail\OrderStatusChanged;
use App\Models\Order;
use App\Models\User;
use Illuminate\Support\Facades\Mail;
use InvalidArgumentException;

class OrderStatusService
{
    /** Statuses whose change is worth an email to the customer by default. */
    public const NOTIFY_BY_DEFAULT = [
        OrderStatus::Preparing,
        OrderStatus::Shipped,
        OrderStatus::Delivered,
        OrderStatus::Confirmed,
        OrderStatus::Cancelled,
        OrderStatus::Refunded,
    ];

    public function transition(Order $order, OrderStatus $to, ?string $note = null, bool $notify = true, ?User $user = null, bool $force = false): Order
    {
        $from = $order->status;

        if (! $force && $from !== $to && ! in_array($to, $from->allowedTransitions(), true)) {
            throw new InvalidArgumentException("{$from->getLabel()} durumundan {$to->getLabel()} durumuna geçilemez.");
        }

        $attributes = ['status' => $to];
        if ($to === OrderStatus::Shipped && ! $order->shipped_at) {
            $attributes['shipped_at'] = now();
        }
        if ($to === OrderStatus::Delivered && ! $order->delivered_at) {
            $attributes['delivered_at'] = now();
        }

        $order->forceFill($attributes)->save();

        $order->histories()->create([
            'from_status' => $from,
            'to_status' => $to,
            'note' => $note ?: null,
            'customer_notified' => $notify,
            'user_id' => $user?->id,
        ]);

        if ($notify) {
            Mail::to($order->customer_email)->send(new OrderStatusChanged($order, $to, $note));
        }

        return $order;
    }

    public function ship(Order $order, CargoCompany $company, string $trackingNumber, ?string $note = null, bool $notify = true, ?User $user = null): Order
    {
        $order->forceFill([
            'cargo_company' => $company,
            'cargo_tracking_number' => trim($trackingNumber),
        ])->save();

        return $this->transition($order, OrderStatus::Shipped, $note, $notify, $user);
    }
}
