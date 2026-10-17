@props(['product'])
@php
    $piece = $product->pieceState();
    $inStock = $product->isInStock();
    $dimmed = $piece ? $piece === \App\Enums\PieceState::Sold : ! $inStock;
    $stock = $product->hasVariants() || $product->isUnique() ? null : $product->availableStock();
    $cover = $product->coverImage();
@endphp
<a href="{{ $product->url() }}" class="group block">
    <div class="relative aspect-[4/5] overflow-hidden rounded-2xl bg-paper-deep">
        @if ($cover)
            <img src="{{ \App\Support\ProductImages::url($cover, 800) }}"
                 @if ($srcset = \App\Support\ProductImages::srcset($cover)) srcset="{{ $srcset }}" sizes="(min-width: 1024px) 25vw, 50vw" @endif
                 alt="{{ $product->name }}" loading="lazy" decoding="async"
                 class="size-full object-cover transition duration-500 group-hover:scale-[1.03] {{ $dimmed ? 'opacity-60 grayscale-[40%]' : '' }}">
        @else
            <div class="flex size-full items-center justify-center text-ink-faint">
                <x-doodle.sun class="size-2/3 opacity-40" />
            </div>
        @endif

        <div class="absolute left-3 top-3 flex flex-col items-start gap-1.5">
            @if ($product->isWorkshop())
                <span class="badge bg-cobalt text-paper">atölye</span>
            @endif
            @if ($piece === \App\Enums\PieceState::Sold)
                <span class="badge bg-ink text-paper">satıldı</span>
            @elseif (! $piece && ! $inStock)
                <span class="badge bg-ink text-paper">tükendi</span>
            @elseif ($stock === 1)
                <span class="badge bg-tile text-paper">son 1 adet</span>
            @elseif ($product->isOnSale())
                <span class="badge bg-tile text-paper">indirim</span>
            @endif
            @if ($product->made_to_order)
                <span class="badge bg-paper/90 text-ink">siparişe özel</span>
            @endif
        </div>
    </div>
    <div class="mt-3 space-y-1 px-0.5">
        <h3 class="font-sans text-[15px] font-medium leading-snug text-ink">{{ $product->name }}</h3>
        <x-shop.price :product="$product" class="text-ink-soft" />
    </div>
</a>
