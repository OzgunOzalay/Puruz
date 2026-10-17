@php
    use App\Enums\PieceState;
    use App\Support\Money;

    $order = $product->pieceOrder();
    $item = $order?->items->firstWhere('product_id', $product->id);
@endphp
<div class="mb-3 flex items-center gap-3">
    @if ($cover = $product->coverImage())
        <img src="{{ \App\Support\ProductImages::url($cover, 200) }}" alt="" class="size-14 rounded-lg object-cover">
    @else
        <div class="flex size-14 items-center justify-center rounded-lg bg-gray-100 text-xs text-gray-400 dark:bg-white/5">foto yok</div>
    @endif
    <div class="text-sm">
        <p class="font-medium text-gray-950 dark:text-white">{{ $product->name }}</p>
        <p class="text-gray-500 dark:text-gray-400">{{ \App\Support\Money::format($product->price) }}</p>
    </div>
</div>
<div class="space-y-1 text-sm">
    <p class="font-medium">{{ \App\Filament\Resources\Products\ProductStatusText::html($product) }}</p>
    <p class="text-gray-500 dark:text-gray-400">
        @if ($product->pieceState() === PieceState::Sold)
            @if ($item) {{ Money::format($item->unit_price) }} fiyatla satıldı @endif
        @else
            {{ number_format($product->view_count, 0, ',', '.') }} görüntülenme
        @endif
    </p>
    @if ($order)
        <a href="{{ \App\Filament\Resources\Orders\OrderResource::getUrl('view', ['record' => $order]) }}" class="text-primary-600 underline dark:text-primary-400">Sipariş {{ $order->number }}</a>
    @endif
</div>
<a href="{{ $product->isReachable() ? $product->url() : $product->previewUrl() }}" target="_blank" class="mt-2 inline-block text-sm text-primary-600 underline dark:text-primary-400">Mağazada nasıl görünecek?</a>
