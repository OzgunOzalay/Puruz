<?php

namespace App\Services\Cart;

use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\WorkshopSession;

final class CartLine
{
    public function __construct(
        public readonly string $key,
        public readonly Product $product,
        public readonly ?ProductVariant $variant,
        public readonly ?WorkshopSession $session,
        public int $quantity,
        public ?string $problem = null,
    ) {}

    public function unitPrice(): int
    {
        return $this->session?->effectivePrice()
            ?? $this->variant?->effectivePrice()
            ?? $this->product->price;
    }

    public function total(): int
    {
        return $this->unitPrice() * $this->quantity;
    }

    public function isWorkshop(): bool
    {
        return $this->product->isWorkshop();
    }

    /** Most units of this line that can be bought now, null meaning unlimited. */
    public function maxQuantity(): ?int
    {
        if ($this->session) {
            return $this->session->seatsLeft();
        }

        return $this->product->availableStock($this->variant);
    }

    public function description(): ?string
    {
        return $this->variant?->name ?? $this->session?->displayLabel();
    }
}
