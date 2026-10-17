<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Services\Cart\CartService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use InvalidArgumentException;

class CartController extends Controller
{
    public function __construct(private readonly CartService $cart) {}

    public function show(): View
    {
        return view('shop.cart', ['cart' => $this->cart]);
    }

    public function add(Request $request): RedirectResponse
    {
        if (! $this->addFromRequest($request)) {
            return back()->withInput();
        }

        return back()->with('cart_added', true);
    }

    /** "Hemen al": add and go straight to checkout, the shortest path from an Instagram link. */
    public function buyNow(Request $request): RedirectResponse
    {
        if (! $this->addFromRequest($request)) {
            return back()->withInput();
        }

        return redirect()->route('checkout.show');
    }

    public function update(Request $request, string $key): RedirectResponse
    {
        $this->cart->update($key, (int) $request->input('quantity', 1));

        return redirect()->route('cart.show');
    }

    public function remove(string $key): RedirectResponse
    {
        $this->cart->remove($key);

        return back();
    }

    public function applyCoupon(Request $request): RedirectResponse
    {
        $request->validate(['code' => ['required', 'string', 'max:50']]);

        try {
            $coupon = $this->cart->applyCoupon($request->string('code'));
        } catch (InvalidArgumentException $e) {
            return back()->withErrors(['code' => $e->getMessage()])->withInput();
        }

        return back()->with('status', "“{$coupon->code}” kuponu uygulandı.");
    }

    public function removeCoupon(): RedirectResponse
    {
        $this->cart->removeCoupon();

        return back();
    }

    private function addFromRequest(Request $request): bool
    {
        $data = $request->validate([
            'product_id' => ['required', 'integer'],
            'quantity' => ['nullable', 'integer', 'min:1', 'max:'.CartService::MAX_QUANTITY],
            'variant_id' => ['nullable', 'integer'],
            'session_id' => ['nullable', 'integer'],
        ]);

        $product = Product::query()->reachable()->find($data['product_id']);
        if (! $product) {
            session()->flash('error', 'Bu ürün şu anda satışta değil.');

            return false;
        }

        try {
            $this->cart->add($product, (int) ($data['quantity'] ?? 1), $data['variant_id'] ?? null, $data['session_id'] ?? null);
        } catch (InvalidArgumentException $e) {
            session()->flash('error', $e->getMessage());

            return false;
        }

        return true;
    }
}
