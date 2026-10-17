<?php

namespace App\Filament\Widgets;

use App\Enums\OrderStatus;
use App\Enums\RequestStatus;
use App\Filament\Resources\Orders\OrderResource;
use App\Filament\Resources\SimilarRequests\SimilarRequestResource;
use App\Models\Order;
use App\Models\SimilarRequest;
use Filament\Widgets\Widget;
use Illuminate\Support\Collection;

class Waiting extends Widget
{
    protected static ?int $sort = 2;

    // Rendered with the page: the dashboard should show what needs doing without a loading step.
    protected static bool $isLazy = false;

    protected string $view = 'filament.widgets.waiting';

    public static function items(): Collection
    {
        $orders = Order::query()->whereIn('status', [OrderStatus::Paid, OrderStatus::Preparing])->get()
            ->map(fn (Order $order) => [
                'type' => 'order',
                'title' => $order->number.' · '.$order->customer_name,
                'meta' => $order->requires_shipping ? 'Kargoya ver' : 'Hazırla',
                'since' => $order->paid_at ?? $order->created_at,
                'url' => OrderResource::getUrl('view', ['record' => $order]),
                'action' => $order->requires_shipping ? 'Kargoya ver' : null,
            ]);

        $requests = SimilarRequest::query()->where('status', RequestStatus::New)->with('product')->get()
            ->map(fn (SimilarRequest $request) => [
                'type' => 'request',
                'title' => $request->product?->name.' gibi',
                'meta' => 'Talep · '.$request->contact,
                'since' => $request->created_at,
                'url' => SimilarRequestResource::getUrl('view', ['record' => $request]),
                'action' => null,
            ]);

        return $orders->concat($requests)->sortBy('since')->values()->take(8);
    }
}
