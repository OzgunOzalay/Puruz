<?php

namespace Tests\Feature;

use App\Enums\CargoCompany;
use App\Enums\OrderStatus;
use App\Filament\Resources\Orders\OrdersSummary;
use App\Filament\Resources\Orders\OrderStatusText;
use App\Filament\Resources\Orders\Pages\ListOrders;
use App\Filament\Resources\Orders\Pages\ViewOrder;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Carbon\CarbonImmutable;
use Filament\Actions\Testing\TestAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;
use Tests\TestCase;

class AdminOrdersTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        $this->travelTo(CarbonImmutable::parse('2026-11-20 15:00', 'Europe/Istanbul'));
        $this->actingAs(User::factory()->create());
    }

    private function order(array $attributes = [], string $item = 'Kobalt Kupa'): Order
    {
        $order = Order::factory()->create($attributes);
        $product = Product::factory()->unique()->create(['name' => $item]);
        $order->items()->create(['product_id' => $product->id, 'product_type' => 'unique', 'name' => $item, 'unit_price' => 50000, 'quantity' => 1, 'line_total' => 50000, 'charged_total' => 50000, 'stock_reserved' => true, 'payment_transaction_id' => '1']);

        return $order->fresh('items');
    }

    // Review focus 5
    public function test_status_phrases(): void
    {
        $fresh = $this->order(['created_at' => now()->subDays(9), 'paid_at' => now()->subDay()]);
        $late = $this->order(['paid_at' => now()->subDays(3)]);
        $shipped = $this->order(['status' => OrderStatus::Shipped, 'cargo_company' => CargoCompany::Aras, 'shipped_at' => now()->subDays(3)]);
        $unpaid = $this->order(['status' => OrderStatus::PendingPayment, 'paid_at' => null, 'reserved_until' => now()->addMinutes(12)]);
        $expired = $this->order(['status' => OrderStatus::PendingPayment, 'paid_at' => null, 'reserved_until' => now()->subMinutes(5)]);

        $this->assertSame(['success', 'Hazırlanacak · 1 gündür'], OrderStatusText::for($fresh));
        $this->assertSame(['warning', 'Hazırlanacak · 3 gündür'], OrderStatusText::for($late));
        $this->assertSame(['success', 'Kargoda · Aras Kargo · 3 gün'], OrderStatusText::for($shipped));
        $this->assertSame(['gray', 'Ödeme bekleniyor · 12 dk kaldı'], OrderStatusText::for($unpaid));
        $this->assertSame(['gray', 'Ödeme bekleniyor · süresi doldu'], OrderStatusText::for($expired));
    }

    public function test_summary_numbers(): void
    {
        $this->order(['paid_at' => now()->subDays(2), 'total' => 59000]);
        $this->order(['status' => OrderStatus::Shipped, 'paid_at' => now()->subDays(1), 'total' => 100000]);
        $this->order(['status' => OrderStatus::PendingPayment, 'paid_at' => null, 'reserved_until' => now()->addMinutes(5)]);

        $stats = OrdersSummary::stats();
        $this->assertSame(1, $stats['to_pack']);
        $this->assertSame(2, $stats['oldest_days']);
        $this->assertSame(1, $stats['shipped']);
        $this->assertSame(2, $stats['week_count']);
        $this->assertSame(159000, $stats['week_total']);
        $this->assertSame(1, $stats['unpaid']);
        $this->get('/yonetim/siparisler')->assertOk()->assertSee('1 sipariş hazırlanacak');
    }

    public function test_ship_from_the_list_and_filter_by_piece(): void
    {
        $kobalt = $this->order([], 'Kobalt Kupa');
        $vazo = $this->order([], 'Uzun Vazo');

        Livewire::test(ListOrders::class)
            ->filterTable('item', ['name' => 'vazo'])
            ->assertCanSeeTableRecords([$vazo])->assertCanNotSeeTableRecords([$kobalt]);

        Livewire::test(ListOrders::class)
            ->callAction(TestAction::make('ship')->table($kobalt), data: ['cargo_company' => 'aras', 'tracking_number' => 'AR1', 'notify' => false]);

        $this->assertSame(OrderStatus::Shipped, $kobalt->fresh()->status);
    }

    public function test_next_step_bar(): void
    {
        $toPack = $this->order();
        $shipped = $this->order(['status' => OrderStatus::Shipped, 'cargo_company' => CargoCompany::Aras, 'shipped_at' => now()->subDay()]);

        $this->assertSame(['Paketleyip kargoya verin', 'ship'], OrderStatusText::nextStep($toPack));
        $this->assertSame(['Kargoda, teslim edildiğinde işaretleyin', 'markDelivered'], OrderStatusText::nextStep($shipped));
        $this->assertNull(OrderStatusText::nextStep($this->order(['status' => OrderStatus::Delivered])));

        $this->get('/yonetim/siparisler/'.$toPack->id)->assertOk()->assertSee('Paketleyip kargoya verin');

        Livewire::test(ViewOrder::class, ['record' => $shipped->id])->callAction('markDelivered');
        $this->assertSame(OrderStatus::Delivered, $shipped->fresh()->status);
    }
}
