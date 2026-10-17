@php($drawerCart = app(\App\Services\Cart\CartService::class))
<div x-data="{ open: true }" x-show="open" x-cloak class="fixed inset-0 z-50" @keydown.escape.window="open = false">
    <div class="absolute inset-0 bg-ink/30" @click="open = false" x-transition.opacity></div>
    <aside class="absolute inset-x-0 bottom-0 max-h-[85dvh] overflow-y-auto rounded-t-3xl bg-paper p-5 pb-[calc(env(safe-area-inset-bottom)+1.25rem)] shadow-2xl sm:inset-y-0 sm:left-auto sm:right-0 sm:w-[420px] sm:rounded-none sm:rounded-l-3xl"
           x-transition:enter="transition duration-300" x-transition:enter-start="translate-y-full sm:translate-y-0 sm:translate-x-full" x-transition:enter-end="translate-y-0 sm:translate-x-0"
           role="dialog" aria-label="Sepet">
        <div class="mb-4 flex items-center justify-between">
            <p class="font-hand text-2xl">sepete eklendi <span class="text-cobalt">✓</span></p>
            <button type="button" @click="open = false" class="rounded-full p-2" aria-label="Kapat"><x-glyph name="close" /></button>
        </div>
        <ul class="divide-y divide-line">
            @foreach ($drawerCart->lines() as $line)
                <li class="flex gap-3 py-3">
                    <img src="{{ $line->product->coverUrl(400) }}" alt="" class="size-16 rounded-xl bg-paper-deep object-cover">
                    <div class="min-w-0 flex-1 text-sm">
                        <p class="font-medium">{{ $line->product->name }}</p>
                        @if ($line->description())<p class="text-ink-soft">{{ $line->description() }}</p>@endif
                        <p class="text-ink-soft">{{ $line->quantity }} × {{ \App\Support\Money::format($line->unitPrice()) }}</p>
                    </div>
                </li>
            @endforeach
        </ul>
        @if ($gap = $drawerCart->freeShippingGap())
            <p class="mt-3 rounded-xl bg-cobalt-soft px-3 py-2 text-sm text-cobalt">Ücretsiz kargo için {{ \App\Support\Money::format($gap) }} daha ekleyin.</p>
        @endif
        <div class="mt-4 flex items-center justify-between text-[15px]">
            <span class="text-ink-soft">Ara toplam</span>
            <span class="font-medium">{{ \App\Support\Money::format($drawerCart->subtotal()) }}</span>
        </div>
        <div class="mt-5 grid gap-2">
            <a href="{{ route('checkout.show') }}" class="btn-primary w-full">Ödemeye geç <x-glyph name="arrow-right" class="size-4" /></a>
            <a href="{{ route('cart.show') }}" class="btn-outline w-full">Sepeti gör</a>
            <button type="button" @click="open = false" class="btn-ghost w-full">Alışverişe devam et</button>
        </div>
    </aside>
</div>
