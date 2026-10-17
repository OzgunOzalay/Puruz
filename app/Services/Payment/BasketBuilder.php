<?php

namespace App\Services\Payment;

use App\Models\Order;

/**
 * Turns an order into iyzico basket lines. iyzico requires the basket prices to add up
 * exactly to the payment price and rejects lines priced at zero, so each line uses its
 * charged_total and zero lines are left out.
 */
class BasketBuilder
{
    public const SHIPPING_ID = 'shipping';

    /**
     * @return array<int, array{id: string, name: string, category: string, type: string, price: int}>
     */
    public static function lines(Order $order): array
    {
        $lines = [];

        foreach ($order->items as $item) {
            if ($item->charged_total <= 0) {
                continue;
            }

            $lines[] = [
                'id' => self::itemId($item->id),
                'name' => mb_substr($item->displayName(), 0, 100),
                'category' => $item->product_type === 'workshop' ? 'Atölye' : 'Seramik',
                'type' => $item->product_type === 'workshop' ? 'VIRTUAL' : 'PHYSICAL',
                'price' => $item->charged_total,
            ];
        }

        if ($order->shipping_total > 0) {
            $lines[] = [
                'id' => self::SHIPPING_ID,
                'name' => 'Kargo',
                'category' => 'Kargo',
                'type' => 'VIRTUAL',
                'price' => $order->shipping_total,
            ];
        }

        return $lines;
    }

    public static function itemId(int $orderItemId): string
    {
        return 'item-'.$orderItemId;
    }
}
