<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Enums\PieceState;
use App\Models\Order;
use App\Models\Product;
use App\Services\Orders\RefundService;
use App\Services\Payment\FakeGateway;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class UniquePieceLifecycleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
    }

    private function placeOrder(Product $product): Order
    {
        $this->post('/sepet', ['product_id' => $product->id])->assertSessionHasNoErrors();
        $this->assertNull(session('error'), (string) session('error'));
        $this->post('/odeme', $this->checkoutData())->assertRedirectContains('/odeme/test/');

        return Order::query()->latest('id')->firstOrFail();
    }

    private function pay(Order $order): void
    {
        FakeGateway::$results[$order->payment_token] = FakeGateway::successFor($order);
        $this->post('/odeme/donus', ['token' => $order->payment_token]);
    }

    public function test_adding_a_piece_twice_keeps_quantity_one(): void
    {
        $piece = Product::factory()->unique()->create();

        $this->post('/sepet', ['product_id' => $piece->id]);
        $this->post('/sepet', ['product_id' => $piece->id, 'quantity' => 3]);

        $this->assertNull(session('error'));
        $this->assertSame([1], array_column(session('cart.items'), 'quantity'));
    }

    public function test_piece_is_reserved_at_checkout_and_sold_when_paid(): void
    {
        $piece = Product::factory()->unique()->create();

        $order = $this->placeOrder($piece);
        $this->assertSame(PieceState::Reserved, $piece->fresh()->pieceState());

        $this->pay($order);
        $this->assertSame(PieceState::Sold, $piece->fresh()->pieceState());
        $this->assertNotNull($piece->fresh()->sold_at);
    }

    public function test_failed_or_expired_payment_makes_the_piece_available_again(): void
    {
        $piece = Product::factory()->unique()->create();

        $order = $this->placeOrder($piece);
        $order->update(['reserved_until' => now()->subMinute()]);
        $this->artisan('shop:release-reservations')->assertSuccessful();

        $this->assertSame(PieceState::Available, $piece->fresh()->pieceState());
    }

    public function test_sold_piece_cannot_be_added_or_checked_out(): void
    {
        $piece = Product::factory()->unique()->create();
        $this->post('/sepet', ['product_id' => $piece->id]);
        $piece->forceFill(['sold_at' => now()])->save();

        $this->post('/odeme', $this->checkoutData())->assertRedirect('/sepet');
        $this->post('/sepet', ['product_id' => $piece->id]);
        $this->assertSame('Üzgünüz, bu parça satıldı.', session('error'));
    }

    public function test_refund_with_restock_makes_the_piece_available_and_without_keeps_it_sold(): void
    {
        $refunds = app(RefundService::class);

        $restocked = Product::factory()->unique()->create();
        $order = $this->placeOrder($restocked);
        $this->pay($order);
        $refunds->refundAll($order->fresh(), 'Vazgeçti', restock: true, notify: false, user: null, ip: '127.0.0.1');
        $this->assertSame(PieceState::Available, $restocked->fresh()->pieceState());

        $broken = Product::factory()->unique()->create();
        $order = $this->placeOrder($broken);
        $this->pay($order);
        $refunds->refundAll($order->fresh(), 'Kırık geldi', restock: false, notify: false, user: null, ip: '127.0.0.1');
        $this->assertSame(PieceState::Sold, $broken->fresh()->pieceState());
    }

    // Review focus 2
    public function test_partial_refund_keeps_the_piece_sold(): void
    {
        $piece = Product::factory()->unique()->create(['price' => 65000]);
        $order = $this->placeOrder($piece);
        $this->pay($order);
        $order = $order->fresh('items');

        app(RefundService::class)->refundPartial($order, $order->items->first(), 10000, 'Küçük çizik', null, '127.0.0.1');

        $this->assertSame(PieceState::Sold, $piece->fresh()->pieceState());
    }

    // Review focus 1
    public function test_late_payment_for_a_piece_sold_meanwhile_is_voided_and_marks_nothing(): void
    {
        $piece = Product::factory()->unique()->create();

        $late = $this->placeOrder($piece);
        $late->update(['reserved_until' => now()->subMinute()]);
        $this->artisan('shop:release-reservations');
        $this->flushSession();

        $winner = $this->placeOrder($piece);
        $this->pay($winner);
        $soldAt = $piece->fresh()->sold_at;

        $this->pay($late);

        $this->assertSame(OrderStatus::Cancelled, $late->fresh()->status);
        $this->assertSame(PieceState::Sold, $piece->fresh()->pieceState());
        $this->assertEquals($soldAt, $piece->fresh()->sold_at);
    }
}
