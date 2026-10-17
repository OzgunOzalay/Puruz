<?php

namespace Tests\Feature;

use App\Enums\ProductStatus;
use App\Enums\ProductType;
use App\Enums\UserRole;
use App\Filament\Resources\Products\Pages\CreateProduct;
use App\Filament\Resources\Products\Pages\EditProduct;
use App\Filament\Resources\Products\Pages\ListProducts;
use App\Filament\Resources\Products\ProductsSummary;
use App\Filament\Resources\Products\ProductStatusText;
use App\Models\Product;
use App\Models\User;
use Carbon\CarbonImmutable;
use Filament\Actions\Testing\TestAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class AdminProductsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(CarbonImmutable::parse('2026-11-20 15:00', 'Europe/Istanbul'));
        $this->actingAs(User::factory()->create(['role' => UserRole::Admin]));
    }

    public function test_state_phrases_read_naturally(): void
    {
        $available = Product::factory()->unique()->create(['published_at' => now()->subDays(12)]);
        $today = Product::factory()->unique()->create(['published_at' => now()->setTime(9, 0)]);
        $sold = Product::factory()->unique()->create(['published_at' => CarbonImmutable::parse('2026-10-16'), 'sold_at' => CarbonImmutable::parse('2026-11-03 10:00')]);
        $reserved = Product::factory()->unique()->create();
        Product::query()->whereKey($reserved->id)->update(['stock' => 0]);
        $draft = Product::factory()->unique()->create(['status' => ProductStatus::Draft]);
        $future = Product::factory()->unique()->create(['published_at' => CarbonImmutable::parse('2026-12-05 10:00')]);

        $this->assertSame(['success', 'Satışta · 12 gündür'], ProductStatusText::for($available));
        $this->assertSame(['success', 'Satışta · bugünden beri'], ProductStatusText::for($today));
        $this->assertSame(['gray', 'Satıldı · 3 Kas, 18 günde'], ProductStatusText::for($sold->fresh()));
        $this->assertSame(['warning', 'Ödeme bekleniyor'], ProductStatusText::for($reserved->fresh()));
        $this->assertSame(['gray', 'Taslak'], ProductStatusText::for($draft));
        $this->assertSame(['gray', 'Yayına girecek · 5 Ara'], ProductStatusText::for($future)); // review focus 4
    }

    public function test_workshop_phrase_summarises_upcoming_sessions(): void
    {
        $workshop = Product::factory()->workshop()->create();
        $workshop->sessions()->create(['starts_at' => '2026-12-03 11:00', 'ends_at' => '2026-12-03 14:00', 'capacity' => 8]);
        $workshop->sessions()->create(['starts_at' => '2026-12-10 11:00', 'ends_at' => '2026-12-10 14:00', 'capacity' => 8, 'seats_reserved' => 2]);

        $this->assertSame(['success', '2 seans · 14 boş yer · sıradaki 3 Ara'], ProductStatusText::for($workshop->fresh()));
    }

    public function test_summary_numbers(): void
    {
        Product::factory()->unique()->create(['price' => 65000]);
        Product::factory()->unique()->create(['price' => 120000]);
        Product::factory()->unique()->create(['price' => 50000, 'published_at' => now()->subDays(20), 'sold_at' => now()->subDays(2)]);
        Product::factory()->unique()->create(['price' => 30000, 'published_at' => now()->subDays(150), 'sold_at' => now()->subMonths(4)]);
        Product::factory()->unique()->create(['status' => ProductStatus::Draft]);
        $reserved = Product::factory()->unique()->create();
        Product::query()->whereKey($reserved->id)->update(['stock' => 0]);

        $stats = ProductsSummary::stats();

        $this->assertSame(2, $stats['available_count']);
        $this->assertSame(185000, $stats['available_value']);
        $this->assertSame(1, $stats['sold_month_count']);
        $this->assertSame(50000, $stats['sold_month_value']);
        $this->assertSame(18, $stats['avg_days']); // only the sale within 90 days counts: 20 − 2
        $this->assertSame(1, $stats['drafts']);
        $this->assertSame(1, $stats['reserved']);
        $this->assertStringContainsString('2 parça satışta', (string) ProductsSummary::html());
    }

    public function test_tabs_filter_pieces(): void
    {
        $available = Product::factory()->unique()->create();
        $sold = Product::factory()->unique()->sold()->create();
        $draft = Product::factory()->unique()->create(['status' => ProductStatus::Draft]);
        $workshop = Product::factory()->workshop()->create();

        Livewire::test(ListProducts::class)
            ->assertCanSeeTableRecords([$available])
            ->assertCanNotSeeTableRecords([$sold, $draft, $workshop])
            ->set('activeTab', 'sold')->assertCanSeeTableRecords([$sold])->assertCanNotSeeTableRecords([$available])
            ->set('activeTab', 'draft')->assertCanSeeTableRecords([$draft])
            ->set('activeTab', 'workshop')->assertCanSeeTableRecords([$workshop])->assertCanNotSeeTableRecords([$available]);

        $this->get('/yonetim/urunler')->assertOk()->assertSee('parça satışta');
    }

    // Review focus 5
    public function test_copying_a_sold_piece_makes_an_available_draft_without_photos(): void
    {
        $sold = Product::factory()->unique()->sold()->create(['images' => ['products/a.jpg'], 'materials' => 'Stoneware']);

        Livewire::test(ListProducts::class)
            ->set('activeTab', 'all')
            ->callAction(TestAction::make('replicate')->table($sold));

        $copy = Product::query()->latest('id')->firstOrFail();
        $this->assertNotSame($sold->id, $copy->id);
        $this->assertSame(ProductStatus::Draft, $copy->status);
        $this->assertNull($copy->sold_at);
        $this->assertSame(1, $copy->stock);
        $this->assertSame([], $copy->images ?? []);
        $this->assertSame('Stoneware', $copy->materials);
        $this->assertNotSame($sold->sku, $copy->sku);
    }

    public function test_creating_a_piece_offers_only_unique_and_workshop(): void
    {
        $this->get('/yonetim/urunler/create')->assertOk()->assertSee('Tek parça')->assertDontSee('Seri üretim');

        Livewire::test(CreateProduct::class)
            ->fillForm([
                'type' => 'unique',
                'name' => 'Benekli Kupa',
                'slug' => 'benekli-kupa',
                'price' => '649,90',
                'status' => 'published',
                'visibility' => 'public',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $piece = Product::query()->where('slug', 'benekli-kupa')->firstOrFail();
        $this->assertSame(ProductType::Unique, $piece->type);
        $this->assertSame(64990, $piece->price);
        $this->assertSame(1, $piece->stock);
        $this->assertSame('PRZ-0001', $piece->sku);
    }

    public function test_sold_piece_edit_page_shows_its_sale(): void
    {
        $sold = Product::factory()->unique()->create(['published_at' => now()->subDays(20), 'sold_at' => now()->subDays(2)]);

        $this->get('/yonetim/urunler/'.$sold->slug.'/edit')->assertOk()
            ->assertSee('Satıldı')
            ->assertSee('mevcut siparişi etkilemez')
            ->assertDontSee('Stok adedi')
            ->assertDontSee('Seri üretim');
    }

    // Review focus 3
    public function test_existing_mass_produced_product_keeps_its_stock_field(): void
    {
        $physical = Product::factory()->create(['stock' => 4]);

        Livewire::test(EditProduct::class, ['record' => $physical->slug])
            ->assertFormFieldVisible('stock')
            ->assertSee('Seri üretim');
    }
}
