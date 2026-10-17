@php
    use App\Enums\PieceState;
    use App\Support\Human;
    use App\Support\Money;

    $order = $product->pieceOrder();
    $item = $order?->items->firstWhere('product_id', $product->id);
    $listedAt = $product->published_at ?? $product->created_at;
@endphp
<div class="space-y-1 text-sm">
    <p class="font-medium">{{ \App\Filament\Resources\Products\ProductStatusText::html($product) }}</p>
    <p class="text-gray-500 dark:text-gray-400">
        @if ($product->pieceState() === PieceState::Sold)
            {{ $product->sold_at->translatedFormat('j F') }}
            @if ($item) · {{ Money::format($item->unit_price) }} @endif
            · {{ Human::took($listedAt, $product->sold_at) }} satıldı
        @else
            {{ number_format($product->view_count, 0, ',', '.') }} görüntülenme
        @endif
    </p>
    @if ($order)
        <a href="{{ \App\Filament\Resources\Orders\OrderResource::getUrl('view', ['record' => $order]) }}" class="text-primary-600 underline dark:text-primary-400">Sipariş {{ $order->number }}</a>
    @endif
</div>
