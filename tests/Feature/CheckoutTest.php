<?php

namespace Tests\Feature;

use App\Enums\CouponType;
use App\Enums\OrderStatus;
use App\Mail\AdminAlert;
use App\Mail\AdminNewOrder;
use App\Mail\OrderConfirmed;
use App\Mail\OrderStatusChanged;
use App\Models\Coupon;
use App\Models\Order;
use App\Models\Product;
use App\Services\Cart\CartService;
use App\Services\Checkout\CheckoutException;
use App\Services\Checkout\CheckoutService;
use App\Services\Payment\FakeGateway;
use App\Services\Payment\PaymentResult;
use App\Support\Settings;
use Database\Seeders\PageSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class CheckoutTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        Settings::set(['shipping_fee' => 9000, 'free_shipping_threshold' => 150000, 'admin_notification_email' => 'atolye@example.com']);
    }

    private function placeOrder(Product $product, int $quantity = 1, array $cart = [], array $data = []): Order
    {
        $this->post('/sepet', ['product_id' => $product->id, 'quantity' => $quantity] + $cart)->assertSessionHasNoErrors();
        $this->assertNull(session('error'), (string) session('error'));
        $this->post('/odeme', $this->checkoutData($data))->assertRedirectContains('/odeme/test/');

        return Order::query()->latest('id')->firstOrFail();
    }

    private function pay(Order $order, ?PaymentResult $result = null): TestResponse
    {
        FakeGateway::$results[$order->payment_token] = $result ?? FakeGateway::successFor($order);

        return $this->post('/odeme/donus', ['token' => $order->payment_token]);
    }

    public function test_order_reserves_stock_and_is_paid_through_callback(): void
    {
        $this->seed(PageSeeder::class);
        $product = Product::factory()->create(['price' => 65000, 'stock' => 3]);

        $order = $this->placeOrder($product, 2);

        $this->assertSame(OrderStatus::PendingPayment, $order->status);
        $this->assertSame(130000, $order->subtotal);
        $this->assertSame(9000, $order->shipping_total);
        $this->assertSame(139000, $order->total);
        $this->assertSame('+905321234567', $order->customer_phone);
        $this->assertStringContainsString('Mesafeli Satış', (string) $order->contract_snapshot);
        $this->assertSame(1, $product->fresh()->stock);

        $this->pay($order)->assertRedirect(route('orders.track', ['token' => $order->tracking_token, 'yeni' => 1]));

        $order->refresh();
        $this->assertSame(OrderStatus::Paid, $order->status);
        $this->assertNotNull($order->items->first()->payment_transaction_id);
        $this->assertNotNull($order->shipping_transaction_id);
        Mail::assertQueued(OrderConfirmed::class, fn ($mail) => $mail->hasTo('ayse@example.com'));
        Mail::assertQueued(AdminNewOrder::class, fn ($mail) => $mail->hasTo('atolye@example.com'));
    }

    public function test_callback_is_idempotent(): void
    {
        $order = $this->placeOrder(Product::factory()->create());

        $this->pay($order);
        $this->pay($order);

        Mail::assertQueued(OrderConfirmed::class, 1);
        $this->assertSame(1, $order->histories()->where('to_status', OrderStatus::Paid)->count());
    }

    public function test_invalid_signature_does_not_mark_order_paid(): void
    {
        $order = $this->placeOrder(Product::factory()->create());
        $good = FakeGateway::successFor($order);

        $this->pay($order, new PaymentResult(true, false, $good->paymentId, $good->conversationId, $good->basketId, $good->paidPrice, $good->price, 'TRY'));

        $this->assertSame(OrderStatus::PendingPayment, $order->fresh()->status);
        $this->assertSame('imza doğrulanamadı', $order->fresh()->payment_error);
        Mail::assertNotQueued(OrderConfirmed::class);
        Mail::assertQueued(AdminAlert::class);
    }

    public function test_amount_or_order_mismatch_is_rejected(): void
    {
        $order = $this->placeOrder(Product::factory()->create());
        $good = FakeGateway::successFor($order);

        $this->pay($order, new PaymentResult(true, true, $good->paymentId, $order->number, $order->number, 100, 100, 'TRY'));
        $this->assertFalse($order->fresh()->status->isPaid());

        $this->pay($order, new PaymentResult(true, true, $good->paymentId, 'PRZ-99-999999', $order->number, $order->total, $order->total, 'TRY'));
        $this->assertFalse($order->fresh()->status->isPaid());
    }

    public function test_failed_payment_releases_stock_and_can_be_retried(): void
    {
        $product = Product::factory()->create(['stock' => 1]);
        $order = $this->placeOrder($product);
        $this->assertSame(0, $product->fresh()->stock);

        $this->pay($order, new PaymentResult(false, true, null, $order->number, $order->number, null, null, 'TRY', errorMessage: 'Yetersiz bakiye'))
            ->assertRedirect(route('payment.failed', $order->tracking_token));

        $this->assertSame(OrderStatus::PaymentFailed, $order->fresh()->status);
        $this->assertSame(1, $product->fresh()->stock);
        $this->get(route('payment.failed', $order->tracking_token))->assertOk()->assertSee('Yetersiz bakiye');

        $this->post(route('payment.retry', $order->tracking_token))->assertRedirectContains('/odeme/test/');
        $this->assertSame(0, $product->fresh()->stock);
        $this->assertSame(OrderStatus::PendingPayment, $order->fresh()->status);
    }

    public function test_cannot_buy_more_than_stock(): void
    {
        $product = Product::factory()->create(['stock' => 1]);

        $this->post('/sepet', ['product_id' => $product->id, 'quantity' => 2])->assertSessionHas('error');
        $this->assertTrue(app(CartService::class)->isEmpty());
    }

    public function test_last_item_cannot_be_sold_twice(): void
    {
        $product = Product::factory()->create(['stock' => 1]);

        $this->post('/sepet', ['product_id' => $product->id]);
        // Someone else buys the last one between adding to cart and checking out.
        $product->refresh()->update(['stock' => 0]);

        $this->post('/odeme', $this->checkoutData())->assertRedirect('/sepet');
        $this->assertSame(0, Order::query()->count());
    }

    public function test_workshop_seats_cannot_be_overbooked(): void
    {
        $workshop = Product::factory()->workshop()->create();
        $session = $workshop->sessions()->create(['starts_at' => now()->addWeek(), 'ends_at' => now()->addWeek()->addHours(3), 'capacity' => 2]);

        $this->post('/sepet', ['product_id' => $workshop->id, 'session_id' => $session->id, 'quantity' => 2]);
        $session->update(['seats_reserved' => 1]);

        // The cart quantity is cut to the one seat left and the customer is sent back to review it.
        $this->post('/odeme', $this->checkoutData(['billing_city' => 'İstanbul', 'billing_district' => 'Kadıköy', 'billing_address' => 'Moda Cad. No 10 D 2']))
            ->assertRedirect('/sepet');
        $this->assertSame(1, $session->fresh()->seats_reserved);
        $this->assertSame(0, Order::query()->count());

        // Meanwhile the last seat is taken too.
        $session->update(['seats_reserved' => 2]);
        $this->post('/odeme', $this->checkoutData(['billing_city' => 'İstanbul', 'billing_district' => 'Kadıköy', 'billing_address' => 'Moda Cad. No 10 D 2']));
        $this->assertSame(2, $session->fresh()->seats_reserved);
        $this->assertSame(0, Order::query()->count());
    }

    public function test_concurrent_reservation_of_last_seat_fails_inside_checkout(): void
    {
        $workshop = Product::factory()->workshop()->create();
        $session = $workshop->sessions()->create(['starts_at' => now()->addWeek(), 'ends_at' => now()->addWeek()->addHours(3), 'capacity' => 1]);
        $this->post('/sepet', ['product_id' => $workshop->id, 'session_id' => $session->id]);

        // Simulates another order grabbing the seat after the cart was loaded but before the locked reservation.
        $cart = app(CartService::class);
        $cart->lines();
        $session->update(['seats_reserved' => 1]);

        $this->expectException(CheckoutException::class);
        app(CheckoutService::class)->placeOrder($cart, $this->checkoutData(['billing_city' => 'İstanbul', 'billing_district' => 'Kadıköy', 'billing_address' => 'Moda Cad. No 10']));
    }

    public function test_workshop_only_order_needs_no_address_and_is_confirmed(): void
    {
        $workshop = Product::factory()->workshop()->create(['price' => 320000]);
        $session = $workshop->sessions()->create(['starts_at' => now()->addWeek(), 'ends_at' => now()->addWeek()->addHours(3), 'capacity' => 8]);

        $this->post('/sepet', ['product_id' => $workshop->id, 'session_id' => $session->id, 'quantity' => 2]);
        $this->post('/odeme', $this->checkoutData([
            'shipping_city' => null, 'shipping_district' => null, 'shipping_address' => null, 'billing_same' => '0',
            'billing_city' => 'İstanbul', 'billing_district' => 'Kadıköy', 'billing_address' => 'Moda Cad. No 10 D 2',
            'participants' => [],
        ]))->assertRedirectContains('/odeme/test/');

        $order = Order::query()->firstOrFail();
        $this->assertFalse($order->requires_shipping);
        $this->assertSame(0, $order->shipping_total);
        $this->assertSame(640000, $order->total);
        $this->assertSame(2, $session->fresh()->seats_reserved);

        $this->pay($order);
        $this->assertSame(OrderStatus::Confirmed, $order->fresh()->status);
        Mail::assertQueued(OrderConfirmed::class, fn (OrderConfirmed $mail) => collect($mail->attachments())->contains(fn ($a) => str_ends_with($a->as, '.ics')));
        $this->get(route('orders.calendar', $order->tracking_token))->assertOk()->assertHeader('Content-Type', 'text/calendar; charset=utf-8');
    }

    public function test_variant_is_required_and_its_stock_is_used(): void
    {
        $product = Product::factory()->create(['stock' => 0]);
        $variant = $product->variants()->create(['name' => 'Kobalt', 'stock' => 2, 'price' => 70000]);

        $this->post('/sepet', ['product_id' => $product->id])->assertSessionHas('error', 'Lütfen bir seçenek belirleyin.');

        $order = $this->placeOrder($product, 1, ['variant_id' => $variant->id]);
        $this->assertSame(70000, $order->subtotal);
        $this->assertSame(1, $variant->fresh()->stock);
        $this->assertSame('Kobalt', $order->items->first()->variant_name);
    }

    public function test_coupon_discount_is_applied_and_counted(): void
    {
        $coupon = Coupon::query()->create(['code' => 'HOSGELDIN10', 'type' => CouponType::Percent, 'value' => 10]);
        $product = Product::factory()->create(['price' => 100000]);

        $this->post('/sepet', ['product_id' => $product->id]);
        $this->post('/sepet/kupon', ['code' => 'hosgeldin10'])->assertSessionHas('status');
        $this->post('/odeme', $this->checkoutData());

        $order = Order::query()->firstOrFail();
        $this->assertSame(10000, $order->discount_total);
        $this->assertSame(100000 - 10000 + 9000, $order->total);
        $this->assertSame(90000, $order->items->first()->charged_total);
        $this->assertSame(1, $coupon->fresh()->used_count);
    }

    public function test_free_shipping_above_threshold(): void
    {
        $order = $this->placeOrder(Product::factory()->create(['price' => 160000]));

        $this->assertSame(0, $order->shipping_total);
    }

    public function test_expired_reservations_are_released(): void
    {
        $product = Product::factory()->create(['stock' => 1]);
        $order = $this->placeOrder($product);
        $order->update(['reserved_until' => now()->subMinute()]);

        $this->artisan('shop:release-reservations')->assertSuccessful();

        $this->assertSame(1, $product->fresh()->stock);
        $this->assertSame(OrderStatus::PaymentFailed, $order->fresh()->status);
    }

    public function test_late_payment_takes_stock_again_or_is_voided(): void
    {
        $product = Product::factory()->create(['stock' => 1]);
        $order = $this->placeOrder($product);
        $order->update(['reserved_until' => now()->subMinute()]);
        $this->artisan('shop:release-reservations');

        // Stock still there: the late payment is accepted.
        $this->pay($order);
        $this->assertSame(OrderStatus::Paid, $order->fresh()->status);
        $this->assertSame(0, $product->fresh()->stock);
        $this->get($order->trackingUrl()); // empties the cart, as the real redirect does
        $this->assertTrue(app(CartService::class)->isEmpty());

        // Second order whose stock was sold to someone else meanwhile: payment is voided.
        $product->refresh()->update(['stock' => 1]);
        $late = $this->placeOrder($product);
        $late->update(['reserved_until' => now()->subMinute()]);
        $this->artisan('shop:release-reservations');
        $product->refresh()->update(['stock' => 0]);

        $this->pay($late);
        $this->assertSame(OrderStatus::Cancelled, $late->fresh()->status);
        $this->assertSame($late->total, $late->fresh()->refunded_total);
        Mail::assertQueued(OrderStatusChanged::class, fn ($mail) => $mail->order->is($late));
    }

    public function test_checkout_validates_required_fields(): void
    {
        $this->post('/sepet', ['product_id' => Product::factory()->create()->id]);

        $this->post('/odeme', $this->checkoutData(['customer_phone' => '123', 'shipping_city' => 'Atlantis', 'accept_terms' => null]))
            ->assertSessionHasErrors(['customer_phone', 'shipping_city', 'accept_terms']);
    }

    public function test_store_closed_blocks_checkout(): void
    {
        Settings::set(['store_closed' => true]);
        $this->post('/sepet', ['product_id' => Product::factory()->create()->id]);

        $this->post('/odeme', $this->checkoutData())->assertSessionHas('error');
        $this->assertSame(0, Order::query()->count());
    }
}
