<?php

namespace App\Http\Controllers;

use App\Http\Requests\CheckoutRequest;
use App\Models\Page;
use App\Services\Cart\CartService;
use App\Services\Checkout\CheckoutException;
use App\Services\Checkout\CheckoutService;
use App\Services\Payment\PaymentException;
use App\Services\Payment\PaymentService;
use App\Support\Settings;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class CheckoutController extends Controller
{
    public function __construct(private readonly CartService $cart) {}

    public function show(): View|RedirectResponse
    {
        if ($this->cart->isEmpty()) {
            return redirect()->route('cart.show');
        }

        return view('shop.checkout', [
            'cart' => $this->cart,
            'districts' => json_decode(file_get_contents(resource_path('data/turkiye-il-ilce.json')), true),
            'storeClosed' => (bool) Settings::get('store_closed'),
            'legalPages' => Page::query()->published()->whereIn('slug', ['on-bilgilendirme-formu', 'mesafeli-satis-sozlesmesi', 'kvkk-aydinlatma-metni'])->get()->keyBy('slug'),
        ]);
    }

    public function store(CheckoutRequest $request, CheckoutService $checkout, PaymentService $payments): RedirectResponse
    {
        if ($this->cart->hasProblems()) {
            return redirect()->route('cart.show')->with('error', 'Sepetinizdeki bazı ürünlerin stoğu değişti, lütfen kontrol edin.');
        }

        try {
            $order = $checkout->placeOrder($this->cart, $request->validated(), [
                'ip' => $request->ip(),
                'user_agent' => $request->userAgent(),
                ...$request->session()->get('utm', []),
            ]);
        } catch (CheckoutException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        // The callback runs without a session, so remember which order belongs to this visitor.
        $request->session()->put('last_order_token', $order->tracking_token);

        try {
            return redirect()->away($payments->start($order, $request->ip()));
        } catch (PaymentException $e) {
            report($e);

            return redirect()->route('payment.failed', $order->tracking_token)->with('error', $e->getMessage());
        }
    }
}
