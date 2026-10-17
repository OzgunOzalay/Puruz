<?php

namespace App\Http\Controllers;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Services\Orders\ReservationService;
use App\Services\Payment\PaymentException;
use App\Services\Payment\PaymentGateway;
use App\Services\Payment\PaymentService;
use App\Support\Settings;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;

class PaymentController extends Controller
{
    /** iyzico posts the checkout token here after the customer finishes on its payment page. */
    public function callback(Request $request, PaymentService $payments): RedirectResponse
    {
        $token = (string) $request->input('token');
        if ($token === '') {
            abort(400);
        }

        try {
            $order = $payments->complete($token, $request->ip());
        } catch (PaymentException $e) {
            report($e);
            $order = Order::query()->where('payment_token', $token)->first();
            if (! $order) {
                abort(404);
            }

            return redirect()->route('payment.failed', $order->tracking_token);
        }

        abort_if($order === null, 404);

        return $order->status->isPaid()
            ? redirect()->route('orders.track', ['token' => $order->tracking_token, 'yeni' => 1])
            : redirect()->route('payment.failed', $order->tracking_token);
    }

    /** Server-to-server notification from iyzico; a backup for customers who close the browser early. */
    public function webhook(Request $request, PaymentGateway $gateway, PaymentService $payments): JsonResponse
    {
        $payload = $request->json()->all();

        if (! $gateway->verifyWebhook($payload, $request->header('X-IYZ-SIGNATURE-V3'))) {
            Log::warning('iyzico webhook imzası geçersiz', ['payload' => $payload]);

            return response()->json(['status' => 'invalid signature'], 400);
        }

        if (! empty($payload['token'])) {
            try {
                $payments->complete((string) $payload['token'], $request->ip());
            } catch (PaymentException $e) {
                report($e);

                return response()->json(['status' => 'retry'], 500);
            }
        }

        return response()->json(['status' => 'ok']);
    }

    public function failed(string $token): View|RedirectResponse
    {
        $order = Order::query()->where('tracking_token', $token)->with('items')->firstOrFail();

        if ($order->status->isPaid()) {
            return redirect()->to($order->trackingUrl());
        }

        return view('shop.payment-failed', compact('order'));
    }

    public function retry(Request $request, string $token, PaymentService $payments, ReservationService $reservations): RedirectResponse
    {
        $order = Order::query()->where('tracking_token', $token)->firstOrFail();

        if ($order->status->isPaid()) {
            return redirect()->to($order->trackingUrl());
        }

        if (! in_array($order->status, [OrderStatus::PendingPayment, OrderStatus::PaymentFailed], true)) {
            return redirect()->route('cart.show')->with('error', 'Bu sipariş artık ödenemez, lütfen yeni bir sipariş oluşturun.');
        }

        if ($order->reservation_released && ! $reservations->reacquire($order)) {
            return redirect()->route('cart.show')->with('error', 'Üzgünüz, siparişinizdeki ürünlerden biri artık stokta yok.');
        }

        $order->forceFill([
            'status' => OrderStatus::PendingPayment,
            'reserved_until' => now()->addMinutes(max(35, (int) Settings::get('reservation_minutes', 30) + 5)),
        ])->save();

        try {
            return redirect()->away($payments->start($order, $request->ip()));
        } catch (PaymentException $e) {
            report($e);

            return back()->with('error', $e->getMessage());
        }
    }

    public function fake(string $token): View
    {
        abort_unless(config('services.payment_gateway') === 'fake', 404);
        $order = Order::query()->where('payment_token', $token)->firstOrFail();

        return view('shop.fake-payment', compact('order', 'token'));
    }

    public function fakeSubmit(Request $request, string $token): View
    {
        abort_unless(config('services.payment_gateway') === 'fake', 404);
        cache()->put('fake-payment:'.$token, $request->input('result') === 'success' ? 'success' : 'fail', 600);

        // Mimics iyzico: an auto-submitting form that posts the token to the callback URL.
        return view('shop.fake-payment-return', compact('token'));
    }
}
