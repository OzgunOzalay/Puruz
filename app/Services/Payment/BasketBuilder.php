<?php

namespace App\Services\Payment;

use App\Models\Order;

/**
 * Turns an order into iyzico basket lines. iyzico requires the basket prices to add up
 * exactly to the payment price and rejects lines priced at zero, so discounts are already
 * spread across items (charged_total) and zero lines are left out.
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

    /**
     * Spreads a discount across line totals proportionally. The rounding remainder goes to the
     * largest line so the result always sums to (sum of lines - discount).
     *
     * @param  array<int|string, int>  $lineTotals
     * @return array<int|string, int>
     */
    public static function distributeDiscount(array $lineTotals, int $discount): array
    {
        $subtotal = array_sum($lineTotals);
        if ($discount <= 0 || $subtotal <= 0) {
            return $lineTotals;
        }

        $discount = min($discount, $subtotal);
        $result = [];
        $allocated = 0;

        foreach ($lineTotals as $key => $total) {
            $share = intdiv($total * $discount, $subtotal);
            $result[$key] = $total - $share;
            $allocated += $share;
        }

        $remainder = $discount - $allocated;
        if ($remainder > 0) {
            arsort($lineTotals);
            foreach (array_keys($lineTotals) as $key) {
                $take = min($remainder, $result[$key]);
                $result[$key] -= $take;
                $remainder -= $take;
                if ($remainder === 0) {
                    break;
                }
            }
        }

        return $result;
    }
}
