<?php

namespace App\Observers;

use App\Jobs\GenerateImageVariants;
use App\Models\Product;

class ProductObserver
{
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
