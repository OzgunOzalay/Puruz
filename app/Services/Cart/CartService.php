<?php

namespace App\Services\Cart;

use App\Enums\ProductType;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\WorkshopSession;
use App\Support\Settings;
use Illuminate\Contracts\Session\Session;
use Illuminate\Support\Collection;
use InvalidArgumentException;

/**
 * Session-based cart (no customer accounts). Prices and stock are always read fresh from
 * the database, the session only remembers what was added.
 */
class CartService
{
    private const KEY = 'cart.items';

    public const MAX_QUANTITY = 20;

    public const SOLD_OUT = 'Tükendi';

    public const PIECE_SOLD = 'Bu parça satıldı';

    private ?Collection $lines = null;

    public function __construct(private readonly Session $session) {}

    public function add(Product $product, int $quantity = 1, ?int $variantId = null, ?int $sessionId = null): CartLine
    {
        $quantity = max(1, min(self::MAX_QUANTITY, $quantity));

        if (! $product->isReachable()) {
            throw new InvalidArgumentException('Bu ürün şu anda satışta değil.');
        }

        $variant = null;
        $workshopSession = null;

        if ($product->isWorkshop()) {
            $workshopSession = $sessionId ? $product->sessions()->whereKey($sessionId)->first() : null;
            if (! $workshopSession?->isBookable()) {
                throw new InvalidArgumentException('Lütfen uygun bir atölye tarihi seçin.');
            }
        } elseif (! $product->isUnique() && $product->variants()->where('is_active', true)->exists()) {
            $variant = $variantId ? $product->variants()->where('is_active', true)->whereKey($variantId)->first() : null;
            if (! $variant) {
                throw new InvalidArgumentException('Lütfen bir seçenek belirleyin.');
            }
        }

        $key = $this->lineKey($product->id, $variant?->id, $workshopSession?->id);
        $items = $this->session->get(self::KEY, []);
        $newQuantity = $product->isUnique() ? 1 : ($items[$key]['quantity'] ?? 0) + $quantity;

        $line = new CartLine($key, $product, $variant, $workshopSession, $newQuantity);
        $max = $line->maxQuantity();
        if ($max !== null && $newQuantity > $max) {
            throw new InvalidArgumentException(match (true) {
                $product->isUnique() => 'Üzgünüz, bu parça satıldı.',
                $max === 0 => 'Üzgünüz, bu ürün tükendi.',
                default => "Bu üründen en fazla {$max} adet alabilirsiniz.",
            });
        }

        $items[$key] = [
            'product_id' => $product->id,
            'variant_id' => $variant?->id,
            'session_id' => $workshopSession?->id,
            'quantity' => min(self::MAX_QUANTITY, $newQuantity),
        ];

        $this->session->put(self::KEY, $items);
        $this->lines = null;

        return $line;
    }

    public function update(string $key, int $quantity): void
    {
        $items = $this->session->get(self::KEY, []);
        if (! isset($items[$key])) {
            return;
        }

        if ($quantity <= 0) {
            unset($items[$key]);
        } else {
            $isUnique = Product::query()->whereKey($items[$key]['product_id'])->value('type') === ProductType::Unique->value;
            $items[$key]['quantity'] = $isUnique ? 1 : min(self::MAX_QUANTITY, $quantity);
        }

        $this->session->put(self::KEY, $items);
        $this->lines = null;
    }

    public function remove(string $key): void
    {
        $this->update($key, 0);
    }

    public function clear(): void
    {
        $this->session->forget(self::KEY);
        $this->lines = null;
    }

    /**
     * Current lines with fresh prices. Lines whose product disappeared are dropped; lines with
     * too little stock are clamped and flagged with a problem message.
     *
     * @return Collection<int, CartLine>
     */
    public function lines(): Collection
    {
        if ($this->lines !== null) {
            return $this->lines;
        }

        $items = $this->session->get(self::KEY, []);
        if ($items === []) {
            return $this->lines = collect();
        }

        $products = Product::query()->reachable()
            ->with(['variants', 'categories'])
            ->whereIn('id', array_column($items, 'product_id'))
            ->get()->keyBy('id');
        $variants = ProductVariant::query()->whereIn('id', array_filter(array_column($items, 'variant_id')))->get()->keyBy('id');
        $sessions = WorkshopSession::query()->whereIn('id', array_filter(array_column($items, 'session_id')))->get()->keyBy('id');

        $lines = collect();
        $changed = false;

        foreach ($items as $key => $item) {
            $product = $products->get($item['product_id']);
            $variant = $item['variant_id'] ? $variants->get($item['variant_id']) : null;
            $session = $item['session_id'] ? $sessions->get($item['session_id']) : null;

            if (! $product || ($item['variant_id'] && ! $variant?->is_active) || ($item['session_id'] && ! $session?->isBookable())) {
                unset($items[$key]);
                $changed = true;

                continue;
            }

            $session?->setRelation('product', $product);
            $variant?->setRelation('product', $product);

            $line = new CartLine($key, $product, $variant, $session, (int) $item['quantity']);
            $max = $line->maxQuantity();

            if ($max === 0) {
                $line->problem = $product->isUnique() ? self::PIECE_SOLD : self::SOLD_OUT;
            } elseif ($max !== null && $line->quantity > $max) {
                $line->quantity = $max;
                $line->problem = "Stokta yalnızca {$max} adet kaldı, adet güncellendi.";
                $items[$key]['quantity'] = $max;
                $changed = true;
            }

            $lines->push($line);
        }

        if ($changed) {
            $this->session->put(self::KEY, $items);
        }

        return $this->lines = $lines;
    }

    public function count(): int
    {
        return (int) array_sum(array_column($this->session->get(self::KEY, []), 'quantity'));
    }

    public function isEmpty(): bool
    {
        return $this->lines()->isEmpty();
    }

    /** True when a line sold out or had its quantity reduced since it was added. */
    public function hasProblems(): bool
    {
        return $this->lines()->contains(fn (CartLine $line) => $line->problem !== null);
    }

    public function hasSoldOutLines(): bool
    {
        return $this->lines()->contains(fn (CartLine $line) => in_array($line->problem, [self::SOLD_OUT, self::PIECE_SOLD], true));
    }

    public function requiresShipping(): bool
    {
        return $this->lines()->contains(fn (CartLine $line) => ! $line->isWorkshop());
    }

    public function subtotal(): int
    {
        return $this->lines()->sum(fn (CartLine $line) => $line->total());
    }

    public function shipping(): int
    {
        return self::shippingFor($this->requiresShipping(), $this->subtotal());
    }

    public static function shippingFor(bool $requiresShipping, int $amount): int
    {
        if (! $requiresShipping) {
            return 0;
        }

        $threshold = Settings::get('free_shipping_threshold');
        if ($threshold && $amount >= (int) $threshold) {
            return 0;
        }

        return (int) Settings::get('shipping_fee', 0);
    }

    public function total(): int
    {
        return $this->subtotal() + $this->shipping();
    }

    /** Amount still needed for free shipping, or null if not applicable. */
    public function freeShippingGap(): ?int
    {
        $threshold = (int) Settings::get('free_shipping_threshold');
        if (! $threshold || ! $this->requiresShipping()) {
            return null;
        }

        $gap = $threshold - ($this->subtotal());

        return $gap > 0 ? $gap : null;
    }

    private function lineKey(int $productId, ?int $variantId, ?int $sessionId): string
    {
        return 'p'.$productId.'-v'.($variantId ?? 0).'-s'.($sessionId ?? 0);
    }
}
