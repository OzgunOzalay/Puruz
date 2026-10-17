<?php

namespace App\Services\Orders;

use App\Enums\ProductType;
use App\Enums\RequestStatus;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\SimilarRequest;
use App\Models\WorkshopSession;
use Illuminate\Support\Facades\DB;

/**
 * Stock and workshop seats are taken when an order is created (pending payment)
 * and given back if the payment fails, the reservation expires, or the order is refunded with restock.
 */
class ReservationService
{
    public function release(Order $order): void
    {
        DB::transaction(function () use ($order) {
            $order = Order::query()->lockForUpdate()->with('items')->findOrFail($order->id);
            if ($order->reservation_released) {
                return;
            }

            foreach ($order->items as $item) {
                $this->restore($item);
            }

            $order->forceFill(['reservation_released' => true])->save();
        });
    }

    /**
     * Takes the reservation again for a payment that arrived after the reservation expired.
     * Returns false if stock or seats are no longer available.
     */
    public function reacquire(Order $order): bool
    {
        try {
            DB::transaction(function () use ($order) {
                $order = Order::query()->lockForUpdate()->with('items')->findOrFail($order->id);
                if (! $order->reservation_released) {
                    return;
                }

                foreach ($order->items as $item) {
                    if ($item->workshop_session_id) {
                        $session = WorkshopSession::query()->lockForUpdate()->find($item->workshop_session_id);
                        if (! $session || $session->seatsLeft() < $item->quantity) {
                            throw new OutOfStockException;
                        }
                        $session->increment('seats_reserved', $item->quantity);
                    } elseif ($item->stock_reserved) {
                        $model = $item->product_variant_id
                            ? ProductVariant::query()->lockForUpdate()->find($item->product_variant_id)
                            : Product::withTrashed()->lockForUpdate()->find($item->product_id);
                        if (! $model || $model->stock < $item->quantity) {
                            throw new OutOfStockException;
                        }
                        $model->decrement('stock', $item->quantity);
                    }
                }

                $order->forceFill(['reservation_released' => false])->save();
            });
        } catch (OutOfStockException) {
            return false;
        }

        return true;
    }

    /** Marks the unique pieces of a paid order as sold. */
    public function markSold(Order $order): void
    {
        $ids = $order->items->where('product_type', ProductType::Unique->value)->pluck('product_id')->filter();

        if ($ids->isNotEmpty()) {
            Product::withTrashed()->whereIn('id', $ids)->whereNull('sold_at')->update(['sold_at' => now()]);

            SimilarRequest::query()->whereIn('created_product_id', $ids)
                ->whereNotIn('status', [RequestStatus::Done, RequestStatus::Cancelled])
                ->update(['status' => RequestStatus::Done]);
        }
    }

    private function restore(OrderItem $item): void
    {
        if ($item->workshop_session_id) {
            WorkshopSession::query()->whereKey($item->workshop_session_id)
                ->update(['seats_reserved' => DB::raw('CASE WHEN seats_reserved >= '.(int) $item->quantity.' THEN seats_reserved - '.(int) $item->quantity.' ELSE 0 END')]);

            return;
        }

        if (! $item->stock_reserved) {
            return;
        }

        if ($item->product_variant_id) {
            ProductVariant::query()->whereKey($item->product_variant_id)->increment('stock', $item->quantity);
        } elseif ($item->product_id) {
            Product::withTrashed()->whereKey($item->product_id)->increment('stock', $item->quantity, ['sold_at' => null]);
        }
    }
}
