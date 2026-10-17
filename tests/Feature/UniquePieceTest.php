<?php

namespace Tests\Feature;

use App\Enums\PieceState;
use App\Enums\ProductType;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UniquePieceTest extends TestCase
{
    use RefreshDatabase;

    public function test_unique_piece_is_saved_with_one_in_stock_and_no_made_to_order(): void
    {
        $piece = Product::factory()->unique()->create(['stock' => 5, 'track_stock' => false, 'made_to_order' => true, 'lead_time_days' => 7]);

        $piece->refresh();
        $this->assertSame(1, $piece->stock);
        $this->assertTrue($piece->track_stock);
        $this->assertFalse($piece->made_to_order);
        $this->assertNull($piece->lead_time_days);
        $this->assertSame(PieceState::Available, $piece->pieceState());

        $piece->update(['stock' => 3]);
        $this->assertSame(1, $piece->fresh()->stock);
    }

    public function test_piece_state_follows_stock_and_sold_date(): void
    {
        $sold = Product::factory()->unique()->sold()->create();
        $reserved = Product::factory()->unique()->create();
        Product::query()->whereKey($reserved->id)->decrement('stock');

        $this->assertSame(0, $sold->fresh()->stock);
        $this->assertSame(PieceState::Sold, $sold->fresh()->pieceState());
        $this->assertSame(PieceState::Reserved, $reserved->fresh()->pieceState());
        $this->assertNull(Product::factory()->create()->pieceState()); // mass-produced: no piece state
    }

    public function test_piece_codes_fill_in_automatically(): void
    {
        $first = Product::factory()->unique()->create(['sku' => null]);
        $second = Product::factory()->unique()->create(['sku' => null]);
        Product::factory()->unique()->create(['sku' => 'PRZ-007']);
        $after = Product::factory()->unique()->create(['sku' => null]);
        $manual = Product::factory()->unique()->create(['sku' => 'KASE-1']);
        $workshop = Product::factory()->workshop()->create(['sku' => null]);

        $this->assertSame('PRZ-0001', $first->sku);
        $this->assertSame('PRZ-0002', $second->sku);
        $this->assertSame('PRZ-0008', $after->sku);
        $this->assertSame('KASE-1', $manual->sku);
        $this->assertNull($workshop->sku);
    }

    public function test_mass_production_type_is_only_offered_when_enabled(): void
    {
        $this->assertSame([ProductType::Unique, ProductType::Workshop], ProductType::creatable());

        config(['shop.mass_production' => true]);
        $this->assertSame([ProductType::Unique, ProductType::Physical, ProductType::Workshop], ProductType::creatable());
    }

    public function test_available_first_puts_sold_pieces_last(): void
    {
        $sold = Product::factory()->unique()->sold()->create(['sort_order' => 0]);
        $available = Product::factory()->unique()->create(['sort_order' => 9]);

        $this->assertSame([$available->id, $sold->id], Product::query()->availableFirst()->ordered()->pluck('id')->all());
    }
}
