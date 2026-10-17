<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Filament\Widgets\Greeting;
use App\Filament\Widgets\RecentSales;
use App\Filament\Widgets\Waiting;
use App\Models\Order;
use App\Models\Product;
use App\Models\SimilarRequest;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminDashboardTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(CarbonImmutable::parse('2026-11-20 09:30', 'Europe/Istanbul'));
        $this->actingAs(User::factory()->create());
    }

    public function test_greeting_sentence(): void
    {
        $this->assertSame('Günaydın. Bekleyen iş yok.', Greeting::sentence());

        Order::factory()->create(['paid_at' => now()->subDay(), 'total' => 485000]);
        SimilarRequest::query()->create(['product_id' => Product::factory()->unique()->sold()->create()->id, 'message' => 'x', 'contact' => 'a@b.co', 'contact_type' => 'email']);

        $this->assertSame('Günaydın. 1 sipariş paketlenmeyi, 1 yeni talep yanıt bekliyor. Bu hafta 4.850 ₺ satış.', Greeting::sentence());

        $this->travelTo(CarbonImmutable::parse('2026-11-20 20:00', 'Europe/Istanbul'));
        $this->assertStringStartsWith('İyi akşamlar.', Greeting::sentence());
    }

    public function test_waiting_list_mixes_orders_and_requests_oldest_first(): void
    {
        $order = Order::factory()->create(['paid_at' => now()->subDays(2), 'customer_name' => 'Ayşe Yılmaz']);
        $request = SimilarRequest::query()->forceCreate(['product_id' => Product::factory()->unique()->sold()->create(['name' => 'Kobalt Kupa'])->id, 'message' => 'x', 'contact' => 'a@b.co', 'contact_type' => 'email', 'created_at' => now()->subDay()]);
        Order::factory()->create(['status' => OrderStatus::Shipped]);

        $items = Waiting::items();

        $this->assertSame(['order', 'request'], $items->pluck('type')->all());
        $this->assertStringContainsString($order->number, $items[0]['title']);
        $this->assertSame('Kobalt Kupa gibi', $items[1]['title']);
    }

    public function test_dashboard_renders_and_source_line(): void
    {
        Order::factory()->create(['paid_at' => now()->subDay(), 'utm_source' => 'instagram', 'utm_medium' => 'story', 'total' => 60000]);
        Order::factory()->create(['paid_at' => now()->subDay(), 'total' => 40000]);

        $this->assertSame('Son 30 gün: %60 instagram story, %40 doğrudan', RecentSales::sourceLine());

        $this->get('/yonetim')->assertOk()
            ->assertSee('Günaydın')
            ->assertSee('Bekleyenler')
            ->assertSee('Yaklaşan atölyeler')
            ->assertSee('Son satışlar')
            ->assertDontSee('Satış kaynağı (son 30 gün)');
    }
}
