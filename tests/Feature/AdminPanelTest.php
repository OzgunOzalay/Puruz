<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Enums\UserRole;
use App\Filament\Pages\ShopSettings;
use App\Filament\Resources\Orders\Pages\ViewOrder;
use App\Filament\Resources\Products\Pages\CreateProduct;
use App\Filament\Resources\WorkshopSessions\Pages\ManageWorkshopSessions;
use App\Filament\Widgets\SalesOverview;
use App\Mail\OrderStatusChanged;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Services\Payment\FakeGateway;
use App\Support\Settings;
use Filament\Actions\Testing\TestAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;
use Tests\TestCase;

class AdminPanelTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['role' => UserRole::Admin]);
    }

    private function paidOrder(array $attributes = []): Order
    {
        $product = Product::factory()->create(['stock' => 0]);
        $order = Order::factory()->create($attributes);
        $order->items()->create([
            'product_id' => $product->id,
            'name' => $product->name,
            'unit_price' => 50000,
            'quantity' => 1,
            'line_total' => 50000,
            'charged_total' => 50000,
            'stock_reserved' => true,
            'payment_transaction_id' => '111',
        ]);
        $order->forceFill(['shipping_transaction_id' => '112'])->save();

        return $order->fresh('items');
    }

    public function test_admin_pages_render(): void
    {
        $this->actingAs($this->admin());
        $product = Product::factory()->create();
        $order = $this->paidOrder();

        foreach (['/yonetim', '/yonetim/urunler', '/yonetim/urunler/create', '/yonetim/urunler/'.$product->slug.'/edit',
            '/yonetim/siparisler', '/yonetim/siparisler/'.$order->id, '/yonetim/kategoriler', '/yonetim/atolye-takvimi',
            '/yonetim/sayfalar', '/yonetim/kullanicilar', '/yonetim/ayarlar', route('admin.orders.print', $order)] as $url) {
            $this->get($url)->assertOk();
        }

        $this->get('/yonetim/kuponlar')->assertNotFound();
    }

    public function test_guests_and_staff_are_restricted(): void
    {
        $this->get('/yonetim')->assertRedirect('/yonetim/login');
        $this->get(route('admin.orders.print', $this->paidOrder()))->assertRedirect('/yonetim/login');

        $this->actingAs(User::factory()->create(['role' => UserRole::Staff]));
        $this->get('/yonetim/siparisler')->assertOk();
        $this->get('/yonetim/ayarlar')->assertForbidden();
        $this->get('/yonetim/kullanicilar')->assertForbidden();

        $this->actingAs(User::factory()->create(['is_active' => false]));
        $this->get('/yonetim')->assertForbidden();
    }

    public function test_staff_cannot_see_refund_action(): void
    {
        $this->actingAs(User::factory()->create(['role' => UserRole::Staff]));

        Livewire::test(ViewOrder::class, ['record' => $this->paidOrder()->id])->assertActionHidden('refund');
    }

    public function test_product_can_be_created_with_prices_in_lira(): void
    {
        $this->actingAs($this->admin());

        Livewire::test(CreateProduct::class)
            ->fillForm([
                'type' => 'unique',
                'name' => 'Benekli Kupa',
                'slug' => 'benekli-kupa',
                'price' => '649,90',
                'status' => 'published',
                'visibility' => 'unlisted',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $product = Product::query()->where('slug', 'benekli-kupa')->firstOrFail();
        $this->assertSame(64990, $product->price);
        $this->get($product->url())->assertOk();
    }

    public function test_ship_action_updates_order_and_emails_customer(): void
    {
        Mail::fake();
        $this->actingAs($this->admin());
        $order = $this->paidOrder();

        Livewire::test(ViewOrder::class, ['record' => $order->id])
            ->callAction('ship', data: ['cargo_company' => 'aras', 'tracking_number' => 'AR987', 'notify' => true])
            ->assertHasNoFormErrors();

        $order->refresh();
        $this->assertSame(OrderStatus::Shipped, $order->status);
        $this->assertSame('AR987', $order->cargo_tracking_number);
        Mail::assertQueued(OrderStatusChanged::class);
    }

    public function test_full_refund_cancels_order_and_restocks(): void
    {
        Mail::fake();
        $this->actingAs($this->admin());
        $order = $this->paidOrder(['paid_at' => now()->subDays(3)]);

        Livewire::test(ViewOrder::class, ['record' => $order->id])
            ->callAction('refund', data: ['mode' => 'full', 'restock' => true, 'notify' => true, 'reason' => 'Müşteri vazgeçti']);

        $order->refresh();
        $this->assertSame(OrderStatus::Refunded, $order->status);
        $this->assertSame($order->total, $order->refunded_total);
        $this->assertSame(2, $order->refunds()->count()); // the item and the shipping fee
        $this->assertSame(1, $order->items->first()->product->fresh()->stock);
    }

    public function test_partial_refund_keeps_order_open(): void
    {
        $this->actingAs($this->admin());
        $order = $this->paidOrder();
        $item = $order->items->first();

        Livewire::test(ViewOrder::class, ['record' => $order->id])
            ->callAction('refund', data: ['mode' => 'partial', 'line' => 'item-'.$item->id, 'amount' => '100']);

        $order->refresh();
        $this->assertSame(OrderStatus::Paid, $order->status);
        $this->assertSame(10000, $order->refunded_total);
        $this->assertSame(10000, $item->fresh()->refunded_total);
    }

    public function test_failed_refund_changes_nothing(): void
    {
        $this->actingAs($this->admin());
        $order = $this->paidOrder(['paid_at' => now()->subDays(3)]);
        FakeGateway::$failNextRefund = true;

        Livewire::test(ViewOrder::class, ['record' => $order->id])
            ->callAction('refund', data: ['mode' => 'partial', 'line' => 'shipping', 'amount' => '90']);

        $this->assertSame(0, $order->fresh()->refunded_total);
        $this->assertSame(OrderStatus::Paid, $order->fresh()->status);
    }

    public function test_session_generator_creates_weekly_slots(): void
    {
        $this->actingAs($this->admin());
        $workshop = Product::factory()->workshop()->create();

        Livewire::test(ManageWorkshopSessions::class)
            ->callAction(TestAction::make('generate')->table(), data: [
                'product_id' => $workshop->id,
                'from' => '2026-11-02',
                'until' => '2026-11-08',
                'weekdays' => [2, 4],
                'slots' => [['start' => '11:00', 'end' => '14:00'], ['start' => '19:00', 'end' => '22:00']],
                'capacity' => 8,
                'label' => 'Kasım grubu',
            ])
            ->assertHasNoFormErrors();

        $sessions = $workshop->sessions()->get();
        $this->assertCount(4, $sessions);
        $this->assertSame('2026-11-03 11:00', $sessions->first()->starts_at->format('Y-m-d H:i'));
        $this->assertSame('2026-11-05 22:00', $sessions->last()->ends_at->format('Y-m-d H:i'));
    }

    public function test_settings_page_saves_money_in_kurus(): void
    {
        $this->actingAs($this->admin());

        Livewire::test(ShopSettings::class)
            ->fillForm(['shipping_fee' => '120', 'free_shipping_threshold' => '2.000', 'shop_name' => 'Pürüz Atölye'])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame(12000, Settings::get('shipping_fee'));
        $this->assertSame(200000, Settings::get('free_shipping_threshold'));
    }

    public function test_dashboard_counts_pieces_for_sale_instead_of_low_stock(): void
    {
        $this->actingAs($this->admin());
        Product::factory()->unique()->count(3)->create();
        Product::factory()->unique()->sold()->create(['sold_at' => now()]);

        Livewire::test(SalesOverview::class)
            ->assertSee('Satışta')
            ->assertSee('3 parça')
            ->assertSee('bu ay 1 parça satıldı')
            ->assertDontSee('Az stok');
    }
}
