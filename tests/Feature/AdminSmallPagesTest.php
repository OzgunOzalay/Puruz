<?php

namespace Tests\Feature;

use App\Filament\Resources\WorkshopSessions\Pages\ManageWorkshopSessions;
use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use App\Models\WorkshopSession;
use Database\Seeders\PageSeeder;
use Filament\Tables\Columns\ToggleColumn;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class AdminSmallPagesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAs(User::factory()->create());
    }

    public function test_categories_summary_and_counts(): void
    {
        $category = Category::query()->create(['name' => 'Kupalar', 'slug' => 'kupalar']);
        $category->products()->attach([Product::factory()->unique()->create()->id, Product::factory()->unique()->sold()->create()->id]);

        $this->get('/yonetim/kategoriler')->assertOk()
            ->assertSee('1 kategori · 2 parça')
            ->assertSee('2 parça, 1’i satışta')
            ->assertSee('Mağazada');
    }

    public function test_pages_summary(): void
    {
        $this->seed(PageSeeder::class);

        $this->get('/yonetim/sayfalar')->assertOk()->assertSeeText('sayfa · en son')->assertSee('Yayında');
    }

    public function test_workshop_list_summary_and_no_toggles(): void
    {
        $workshop = Product::factory()->workshop()->create();
        WorkshopSession::query()->create(['product_id' => $workshop->id, 'starts_at' => now()->addDays(3)->setTime(11, 0), 'ends_at' => now()->addDays(3)->setTime(14, 0), 'capacity' => 8, 'seats_reserved' => 5]);

        $this->get('/yonetim/atolye-takvimi/liste')->assertOk()->assertSeeText('1 seans · 3 boş yer · sıradaki')->assertSeeText('5/8 dolu · 3 yer kaldı');

        $columns = Livewire::test(ManageWorkshopSessions::class)->instance()->getTable()->getColumns();
        $this->assertEmpty(array_filter($columns, fn ($c) => $c instanceof ToggleColumn));
    }

    public function test_settings_summary(): void
    {
        $this->get('/yonetim/ayarlar')->assertOk()->assertSee('Mağaza açık')->assertSee('ödemeler');
    }
}
