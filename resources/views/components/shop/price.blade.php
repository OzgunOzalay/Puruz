@props(['product', 'size' => 'base'])
@php($sizes = ['base' => 'text-[15px]', 'lg' => 'text-2xl'])
<div {{ $attributes->merge(['class' => 'flex items-baseline gap-2']) }}>
    @if ($product->isWorkshop())
        <span class="text-ink-soft {{ $size === 'lg' ? 'text-base' : 'text-sm' }}">kişi başı</span>
    @endif
    <span class="font-medium {{ $sizes[$size] }}">{{ \App\Support\Money::format($product->price) }}</span>
    @if ($product->isOnSale())
        <span class="text-ink-faint line-through {{ $size === 'lg' ? 'text-lg' : 'text-sm' }}">{{ \App\Support\Money::format($product->compare_at_price) }}</span>
    @endif
</div>
