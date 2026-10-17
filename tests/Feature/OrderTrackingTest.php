<?php

namespace Tests\Feature;

use App\Enums\CargoCompany;
use App\Enums\OrderStatus;
use App\Mail\OrderStatusChanged;
use App\Mail\TrackingLinks;
use App\Models\Order;
use App\Services\Orders\OrderStatusService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class OrderTrackingTest extends TestCase
{
    use RefreshDatabase;

    public function test_tracking_page_requires_the_exact_token(): void
    {
        $order = Order::factory()->create();

        $this->get('/siparis/'.$order->tracking_token)->assertOk()->assertSee($order->number)->assertSee('noindex', false);
        $this->get('/siparis/'.substr($order->tracking_token, 0, -1).'x')->assertNotFound();
        $this->assertSame(40, strlen($order->tracking_token));
    }

    public function test_shipping_updates_tracking_page_and_notifies_customer(): void
    {
        Mail::fake();
        $order = Order::factory()->create();

        app(OrderStatusService::class)->ship($order, CargoCompany::Yurtici, 'YK123456', 'Kırılmasın diye iki kat sardık.');

        $this->assertSame(OrderStatus::Shipped, $order->fresh()->status);
        Mail::assertQueued(OrderStatusChanged::class, fn ($mail) => $mail->status === OrderStatus::Shipped);
        $this->get($order->trackingUrl())
            ->assertSee('YK123456')
            ->assertSee('yurticikargo.com', false)
            ->assertSee('Kırılmasın diye iki kat sardık.');
    }

    public function test_invalid_transition_is_refused(): void
    {
        $order = Order::factory()->create(['status' => OrderStatus::PendingPayment]);

        $this->expectException(\InvalidArgumentException::class);
        app(OrderStatusService::class)->transition($order, OrderStatus::Shipped);
    }

    public function test_lookup_emails_link_without_revealing_the_order(): void
    {
        Mail::fake();
        $order = Order::factory()->create();

        $this->post('/siparis-sorgula', ['number' => strtolower($order->number), 'email' => 'AYSE@example.com'])
            ->assertSessionHas('status')
            ->assertSessionMissing('order');
        Mail::assertQueued(TrackingLinks::class, fn ($mail) => $mail->hasTo('ayse@example.com'));

        $this->post('/siparis-sorgula', ['number' => $order->number, 'email' => 'baskasi@example.com'])->assertSessionHas('status');
        Mail::assertQueued(TrackingLinks::class, 1);
    }
}
