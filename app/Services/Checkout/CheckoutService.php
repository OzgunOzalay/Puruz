<?php

namespace App\Services\Checkout;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\WorkshopSession;
use App\Services\Cart\CartLine;
use App\Services\Cart\CartService;
use App\Support\Settings;
use Illuminate\Support\Facades\DB;

class CheckoutService
{
    /** Smallest amount iyzico will charge, in kuruş. */
    public const MINIMUM_TOTAL = 100;

    /**
     * Creates a pending order from the cart and reserves stock and seats.
     *
     * @param  array<string, mixed>  $data  validated checkout form data
     * @param  array<string, string|null>  $context  ip, user_agent and utm values
     */
    public function placeOrder(CartService $cart, array $data, array $context = []): Order
    {
        $lines = $cart->lines();
        if ($lines->isEmpty()) {
            throw new CheckoutException('Sepetiniz boş.');
        }

        if (Settings::get('store_closed')) {
            throw new CheckoutException(Settings::get('store_closed_message'));
        }

        $order = DB::transaction(function () use ($lines, $data, $context) {
            $reserved = [];
            foreach ($lines as $line) {
                $reserved[$line->key] = $this->reserve($line);
            }

            $subtotal = $lines->sum(fn (CartLine $line) => $line->total());
            $requiresShipping = $lines->contains(fn (CartLine $line) => ! $line->isWorkshop());
            $shipping = CartService::shippingFor($requiresShipping, $subtotal);
            $total = $subtotal + $shipping;

            if ($total < self::MINIMUM_TOTAL) {
                throw new CheckoutException('Sipariş tutarı ödeme alınabilecek en düşük tutarın altında.');
            }

            $billingSame = (bool) ($data['billing_same'] ?? true);
            $corporate = ($data['billing_type'] ?? 'individual') === 'corporate';

            $order = Order::query()->create([
                'status' => OrderStatus::PendingPayment,
                'customer_name' => $data['customer_name'],
                'customer_email' => mb_strtolower($data['customer_email']),
                'customer_phone' => self::normalizePhone($data['customer_phone']),
                'identity_number' => $data['identity_number'] ?? null,
                'requires_shipping' => $requiresShipping,
                'shipping_name' => $requiresShipping ? ($data['shipping_name'] ?? null) ?: $data['customer_name'] : null,
                'shipping_city' => $requiresShipping ? $data['shipping_city'] : null,
                'shipping_district' => $requiresShipping ? $data['shipping_district'] : null,
                'shipping_address' => $requiresShipping ? $data['shipping_address'] : null,
                'shipping_postal_code' => $requiresShipping ? ($data['shipping_postal_code'] ?? null) : null,
                'billing_type' => $corporate ? 'corporate' : 'individual',
                'billing_name' => $billingSame && $requiresShipping ? (($data['shipping_name'] ?? null) ?: $data['customer_name']) : (($data['billing_name'] ?? null) ?: $data['customer_name']),
                'billing_city' => $billingSame && $requiresShipping ? $data['shipping_city'] : ($data['billing_city'] ?? null),
                'billing_district' => $billingSame && $requiresShipping ? $data['shipping_district'] : ($data['billing_district'] ?? null),
                'billing_address' => $billingSame && $requiresShipping ? $data['shipping_address'] : ($data['billing_address'] ?? null),
                'billing_company' => $corporate ? ($data['billing_company'] ?? null) : null,
                'billing_tax_office' => $corporate ? ($data['billing_tax_office'] ?? null) : null,
                'billing_tax_number' => $corporate ? ($data['billing_tax_number'] ?? null) : null,
                'subtotal' => $subtotal,
                'shipping_total' => $shipping,
                'total' => $total,
                'customer_note' => $data['customer_note'] ?? null,
                'terms_accepted_at' => now(),
                'marketing_consent' => (bool) ($data['marketing_consent'] ?? false),
                'ip_address' => $context['ip'] ?? null,
                'user_agent' => isset($context['user_agent']) ? mb_substr($context['user_agent'], 0, 500) : null,
                'utm_source' => $context['utm_source'] ?? null,
                'utm_medium' => $context['utm_medium'] ?? null,
                'utm_campaign' => $context['utm_campaign'] ?? null,
                // A little longer than iyzico's checkout token lifetime (30 minutes).
                'reserved_until' => now()->addMinutes(max(35, (int) Settings::get('reservation_minutes', 30) + 5)),
            ]);

            foreach ($lines as $line) {
                $order->items()->create([
                    'product_id' => $line->product->id,
                    'product_variant_id' => $line->variant?->id,
                    'workshop_session_id' => $line->session?->id,
                    'product_type' => $line->product->type->value,
                    'name' => $line->product->name,
                    'variant_name' => $line->variant?->name,
                    'session_label' => $line->session?->displayLabel(),
                    'sku' => $line->variant?->sku ?? $line->product->sku,
                    'unit_price' => $line->unitPrice(),
                    'quantity' => $line->quantity,
                    'line_total' => $line->total(),
                    'charged_total' => $line->total(),
                    'stock_reserved' => $reserved[$line->key],
                    'participants' => $line->isWorkshop() ? $this->participants($data, $line) : null,
                ]);
            }

            $order->histories()->create([
                'to_status' => OrderStatus::PendingPayment,
                'note' => 'Sipariş oluşturuldu, ödeme bekleniyor.',
            ]);

            return $order;
        });

        $order->load('items');
        $order->forceFill(['contract_snapshot' => view('contracts.snapshot', ['order' => $order])->render()])->save();

        return $order;
    }

    /** Takes stock or seats for a cart line. Returns whether product/variant stock was decremented. */
    private function reserve(CartLine $line): bool
    {
        if ($line->session) {
            $session = WorkshopSession::query()->lockForUpdate()->find($line->session->id);
            if (! $session || ! $session->isBookable() || $session->seatsLeft() < $line->quantity) {
                throw new CheckoutException("“{$line->product->name}” için seçtiğiniz tarihte yeterli kontenjan kalmadı.");
            }
            $session->increment('seats_reserved', $line->quantity);

            return false;
        }

        if (! $line->product->track_stock) {
            return false;
        }

        if ($line->variant) {
            $model = ProductVariant::query()->lockForUpdate()->find($line->variant->id);
        } elseif ($line->product->made_to_order) {
            return false;
        } else {
            $model = Product::query()->lockForUpdate()->find($line->product->id);
        }

        if (! $model || $model->stock < $line->quantity) {
            throw new CheckoutException($line->product->isUnique()
                ? "Üzgünüz, “{$line->product->name}” satıldı."
                : "Üzgünüz, “{$line->product->name}” için yeterli stok kalmadı.");
        }

        $model->decrement('stock', $line->quantity);

        return true;
    }

    private function participants(array $data, CartLine $line): array
    {
        $names = $data['participants'][$line->key] ?? [];
        $names = array_values(array_filter(array_map('trim', (array) $names)));

        return array_slice($names ?: [$data['customer_name']], 0, $line->quantity);
    }

    /** Normalizes Turkish mobile numbers to +905XXXXXXXXX, which iyzico expects. */
    public static function normalizePhone(string $phone): string
    {
        $digits = preg_replace('/\D+/', '', $phone);

        if (str_starts_with($digits, '90') && strlen($digits) === 12) {
            return '+'.$digits;
        }
        if (str_starts_with($digits, '0') && strlen($digits) === 11) {
            return '+9'.$digits;
        }
        if (strlen($digits) === 10) {
            return '+90'.$digits;
        }

        return '+'.$digits;
    }
}
