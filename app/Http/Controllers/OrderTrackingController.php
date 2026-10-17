<?php

namespace App\Http\Controllers;

use App\Mail\TrackingLinks;
use App\Models\Order;
use App\Services\Cart\CartService;
use App\Support\Ics;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Mail;
use Illuminate\View\View;

class OrderTrackingController extends Controller
{
    public function show(Request $request, string $token, CartService $cart): View
    {
        $order = Order::query()->where('tracking_token', $token)
            ->with(['items.session', 'histories'])->firstOrFail();

        // First visit after a successful payment: the cart has been bought, empty it.
        if ($order->status->isPaid() && $request->session()->get('last_order_token') === $order->tracking_token) {
            $cart->clear();
            $request->session()->forget('last_order_token');
        }

        return view('orders.track', [
            'order' => $order,
            'justPaid' => $request->boolean('yeni') && $order->status->isPaid(),
        ]);
    }

    public function calendar(string $token): Response
    {
        $order = Order::query()->where('tracking_token', $token)->firstOrFail();
        abort_unless($order->status->isPaid() && ($ics = Ics::forOrder($order)), 404);

        return response($ics, 200, [
            'Content-Type' => 'text/calendar; charset=utf-8',
            'Content-Disposition' => 'attachment; filename="atolye-'.$order->number.'.ics"',
        ]);
    }

    public function lookup(): View
    {
        return view('orders.lookup');
    }

    /**
     * Sends the tracking link by email instead of showing the order, so knowing someone's
     * order number and email is not enough to see their address.
     */
    public function sendLink(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'number' => ['required', 'string', 'max:30'],
            'email' => ['required', 'email', 'max:190'],
        ]);

        $order = Order::query()
            ->where('number', mb_strtoupper(trim($data['number'])))
            ->where('customer_email', mb_strtolower(trim($data['email'])))
            ->first();

        if ($order) {
            Mail::to($order->customer_email)->send(new TrackingLinks($order));
        }

        return back()->with('status', 'Bilgiler bir siparişle eşleşiyorsa, takip bağlantısı e-posta adresinize gönderildi.');
    }
}
