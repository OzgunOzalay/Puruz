<?php

namespace App\Observers;

use App\Enums\ProductType;
use App\Jobs\GenerateImageVariants;
use App\Models\Product;

class ProductObserver
{
    public function creating(Product $product): void
    {
        if ($product->type !== ProductType::Workshop && blank($product->sku)) {
            $product->sku = Product::nextSku();
        }
    }

    /** A unique piece is one item: stock 1 while unsold, 0 while held by an order or sold. */
    public function saving(Product $product): void
    {
        if (! $product->isUnique()) {
            return;
        }

        $product->track_stock = true;
        $product->made_to_order = false;
        $product->lead_time_days = null;
        $product->stock = match (true) {
            $product->sold_at !== null => 0,
            ! $product->exists => 1,
            default => max(0, min(1, (int) $product->stock)),
        };
    }

    public function saved(Product $product): void
    {
        if (! $product->wasChanged(['images', 'og_image']) && ! $product->wasRecentlyCreated) {
            return;
        }

        $paths = array_values(array_filter([...($product->images ?? []), $product->og_image]));
        if ($paths !== []) {
            GenerateImageVariants::dispatch($paths);
        }
    }
}
