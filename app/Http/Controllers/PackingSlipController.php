<?php

namespace App\Http\Controllers;

use App\Models\Order;
use Illuminate\View\View;

class PackingSlipController extends Controller
{
    public function show(Order $order): View
    {
        abort_unless(auth()->user()?->is_active, 403);

        return view('admin.packing-slip', ['order' => $order->load('items')]);
    }
}
