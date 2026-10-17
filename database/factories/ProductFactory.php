<?php

namespace Database\Factories;

use App\Enums\ProductStatus;
use App\Enums\ProductType;
use App\Enums\ProductVisibility;
use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<Product> */
class ProductFactory extends Factory
{
    public function definition(): array
    {
        $name = Str::title(fake()->unique()->words(2, true));

        return [
            'type' => ProductType::Physical,
            'name' => $name,
            'slug' => Str::slug($name).'-'.Str::lower(Str::random(4)),
            'price' => 50000,
            'stock' => 5,
            'track_stock' => true,
            'status' => ProductStatus::Published,
            'visibility' => ProductVisibility::Public,
            'published_at' => now()->subDay(),
        ];
    }

    public function workshop(): static
    {
        return $this->state(['type' => ProductType::Workshop, 'track_stock' => false, 'price' => 320000]);
    }
}
