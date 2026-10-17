@extends('layouts.shop')

@use('App\Support\Money')
@use('App\Support\ProductImages')
@php
    $images = $product->images ?? [];
    $variants = $product->variants;
    $sessions = $product->upcomingSessions;
    $bookable = $sessions->filter(fn ($s) => $s->isBookable());
    $inStock = $product->isInStock();
    $piece = $product->pieceState();
    $isSold = $piece === \App\Enums\PieceState::Sold;
    $isReserved = $piece === \App\Enums\PieceState::Reserved;
    $stock = $variants->isEmpty() && ! $product->isUnique() ? $product->availableStock() : null;
    $whatsapp = $settings['whatsapp_number'] ?? null;
    $waText = rawurlencode('Merhaba, "'.$product->name.'" hakkında bilgi almak istiyorum: '.$product->url());
    $defaultVariant = old('variant_id', $variants->first(fn ($v) => ! $product->track_stock || $v->stock > 0)?->id);
    $defaultSession = old('session_id', $bookable->first()?->id);
@endphp

@section('title', $product->seo_title ?: $product->name)
@section('description', $product->seo_description ?: ($product->short_description ?: strip_tags((string) $product->description)))
@section('canonical', $product->url())
@if ($preview)
    @section('noindex', '1')
@endif

@section('og')
    <meta property="og:type" content="product">
    @if ($og = $product->ogImageUrl())
        <meta property="og:image" content="{{ $og }}">
    @endif
    <meta property="product:price:amount" content="{{ Money::toDecimal($product->price) }}">
    <meta property="product:price:currency" content="TRY">
@endsection

@push('head')
    <script type="application/ld+json">{!! json_encode(array_filter([
        '@context' => 'https://schema.org',
        '@type' => 'Product',
        'name' => $product->name,
        'description' => $product->short_description ?: strip_tags((string) $product->description),
        'image' => array_map(fn ($p) => ProductImages::url($p, 1600), $images),
        'sku' => $product->sku,
        'brand' => ['@type' => 'Brand', 'name' => $settings['shop_name']],
        'offers' => [
            '@type' => 'Offer',
            'url' => $product->url(),
            'priceCurrency' => 'TRY',
            'price' => Money::toDecimal($product->price),
            'availability' => $inStock ? 'https://schema.org/InStock' : ($isSold ? 'https://schema.org/SoldOut' : 'https://schema.org/OutOfStock'),
        ],
    ]), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) !!}</script>
@endpush

@section('content')
    @if ($preview)
        <div class="bg-cobalt px-4 py-2 text-center text-sm text-paper">Önizleme: bu ürün müşterilere {{ $product->isReachable() ? 'açık' : 'henüz görünmüyor' }}.</div>
    @endif

    <form method="POST" action="{{ route('cart.add') }}" id="buy-form"
          x-data="{ variant: '{{ $defaultVariant }}', session: '{{ $defaultSession }}', qty: {{ (int) old('quantity', 1) }} }">
        @csrf
        <input type="hidden" name="product_id" value="{{ $product->id }}">

        <div class="container-shop grid gap-8 pt-4 sm:pt-8 lg:grid-cols-[1.15fr_1fr] lg:gap-14">
            {{-- Gallery: native scroll-snap so swiping feels right inside Instagram's in-app browser. --}}
            <div class="-mx-4 sm:mx-0 lg:sticky lg:top-24 lg:self-start" x-data="gallery({{ max(1, count($images)) }})">
                <div x-ref="track" @scroll.debounce.50ms="onScroll($el)"
                     class="flex aspect-[4/5] snap-x snap-mandatory overflow-x-auto scrollbar-none sm:rounded-[2rem]">
                    @forelse ($images as $index => $image)
                        <img src="{{ ProductImages::url($image, 1600) }}"
                             @if ($srcset = ProductImages::srcset($image)) srcset="{{ $srcset }}" sizes="(min-width: 1024px) 55vw, 100vw" @endif
                             alt="{{ $product->name }} – görsel {{ $index + 1 }}"
                             class="size-full shrink-0 snap-center bg-paper-deep object-cover"
                             @if ($index === 0) fetchpriority="high" @else loading="lazy" @endif>
                    @empty
                        <div class="flex size-full shrink-0 items-center justify-center bg-paper-deep"><x-doodle.sun class="size-1/2 text-ink-faint" /></div>
                    @endforelse
                </div>
                @if (count($images) > 1)
                    <div class="mt-3 flex justify-center gap-1.5">
                        @foreach ($images as $index => $image)
                            <button type="button" @click="go({{ $index }})" class="h-1.5 rounded-full transition-all"
                                    :class="active === {{ $index }} ? 'w-6 bg-ink' : 'w-1.5 bg-ink/25'" aria-label="Görsel {{ $index + 1 }}"></button>
                        @endforeach
                    </div>
                    <div class="mt-4 hidden grid-cols-5 gap-3 lg:grid">
                        @foreach ($images as $index => $image)
                            <button type="button" @click="go({{ $index }})" class="overflow-hidden rounded-xl ring-ink transition" :class="active === {{ $index }} && 'ring-2'">
                                <img src="{{ ProductImages::url($image, 400) }}" alt="" class="aspect-square w-full object-cover" loading="lazy">
                            </button>
                        @endforeach
                    </div>
                @endif
            </div>

            <div class="pb-6">
                @if ($product->categories->isNotEmpty())
                    <div class="mb-3 flex flex-wrap gap-2">
                        @foreach ($product->categories as $category)
                            <a href="{{ $category->url() }}" class="chip lowercase hover:border-ink hover:text-ink">{{ $category->name }}</a>
                        @endforeach
                    </div>
                @endif

                <h1 class="text-4xl lowercase leading-[1.05] sm:text-5xl">{{ $product->name }}</h1>
                <x-shop.price :product="$product" size="lg" class="mt-4" />

                <div class="mt-3 flex flex-wrap gap-2 text-sm">
                    @if ($isSold)
                        <span class="badge bg-ink text-paper">satıldı</span>
                    @elseif ($isReserved)
                        <span class="badge bg-tile text-paper">şu an başka birinin sepetinde</span>
                    @elseif (! $inStock)
                        <span class="badge bg-ink text-paper">{{ $product->isWorkshop() ? 'kontenjan dolu' : 'tükendi' }}</span>
                    @elseif ($stock !== null && $stock <= 3)
                        <span class="badge bg-tile text-paper">son {{ $stock }} adet</span>
                    @endif
                    @if ($product->made_to_order && ! $product->isUnique())
                        <span class="badge bg-cobalt-soft text-cobalt">siparişe özel üretilir{{ $product->lead_time_days ? ' · '.$product->lead_time_days.' gün' : '' }}</span>
                    @endif
                </div>

                @if ($product->short_description)
                    <p class="mt-5 text-[17px] leading-relaxed text-ink-soft">{{ $product->short_description }}</p>
                @endif

                {{-- Variant (e.g. glaze colour) --}}
                @if ($variants->isNotEmpty())
                    <fieldset class="mt-7">
                        <legend class="label">Seçenek</legend>
                        <div class="flex flex-wrap gap-2">
                            @foreach ($variants as $variant)
                                @php($soldOut = $product->track_stock && $variant->stock <= 0)
                                <label @class(['cursor-pointer', 'pointer-events-none opacity-40' => $soldOut])>
                                    <input type="radio" name="variant_id" value="{{ $variant->id }}" x-model="variant" class="peer sr-only" @disabled($soldOut)>
                                    <span class="inline-flex items-center gap-2 rounded-full border-[1.5px] border-line px-4 py-2 text-[15px] peer-checked:border-ink peer-checked:bg-ink peer-checked:text-paper peer-focus-visible:outline-2 peer-focus-visible:outline-cobalt">
                                        {{ $variant->name }}
                                        @if ($variant->price && $variant->price !== $product->price)
                                            <span class="opacity-70">{{ Money::format($variant->price) }}</span>
                                        @endif
                                        @if ($soldOut) <span class="text-xs">(tükendi)</span> @endif
                                    </span>
                                </label>
                            @endforeach
                        </div>
                    </fieldset>
                @endif

                {{-- Workshop dates --}}
                @if ($product->isWorkshop())
                    <fieldset class="mt-7">
                        <legend class="label">Tarih seçin</legend>
                        @if ($sessions->isEmpty())
                            <p class="rounded-2xl bg-paper-deep px-4 py-3 text-[15px] text-ink-soft">Yeni tarihler çok yakında. Haber almak için bize WhatsApp’tan yazabilirsiniz.</p>
                        @else
                            <div class="grid gap-2">
                                @foreach ($sessions as $session)
                                    @php($full = ! $session->isBookable())
                                    <label @class(['cursor-pointer', 'pointer-events-none opacity-45' => $full])>
                                        <input type="radio" name="session_id" value="{{ $session->id }}" x-model="session" class="peer sr-only" @disabled($full)>
                                        <span class="flex items-center justify-between gap-3 rounded-2xl border-[1.5px] border-line bg-white/50 px-4 py-3 peer-checked:border-ink peer-checked:bg-white peer-focus-visible:outline-2 peer-focus-visible:outline-cobalt">
                                            <span>
                                                <span class="block font-medium">{{ $session->starts_at->translatedFormat('j F l') }}</span>
                                                <span class="block text-sm text-ink-soft">
                                                    {{ $session->starts_at->format('H:i') }}–{{ $session->ends_at->format('H:i') }}
                                                    @if ($session->label) · {{ $session->label }} @endif
                                                    @if ($session->schedule_note) · {{ $session->schedule_note }} @endif
                                                </span>
                                            </span>
                                            <span class="shrink-0 text-right text-sm">
                                                @if ($full)
                                                    <span class="text-ink-soft">dolu</span>
                                                @else
                                                    @if ($session->price && $session->price !== $product->price)
                                                        <span class="block font-medium">{{ Money::format($session->price) }}</span>
                                                    @endif
                                                    <span @class(['block', 'text-tile' => $session->seatsLeft() <= 2, 'text-ink-soft' => $session->seatsLeft() > 2])>{{ $session->seatsLeft() }} yer kaldı</span>
                                                @endif
                                            </span>
                                        </span>
                                    </label>
                                @endforeach
                            </div>
                        @endif
                    </fieldset>
                @endif

                @if ($inStock)
                    @if ($product->isUnique())
                        <input type="hidden" name="quantity" value="1">
                    @else
                    <div class="mt-7 flex items-center gap-3">
                        <span class="label mb-0">{{ $product->isWorkshop() ? 'Kişi' : 'Adet' }}</span>
                        <div class="flex items-center rounded-full border-[1.5px] border-line">
                            <button type="button" class="p-2.5" @click="qty = Math.max(1, qty - 1)" aria-label="Azalt"><x-glyph name="minus" class="size-4" /></button>
                            <input type="number" name="quantity" x-model.number="qty" min="1" max="{{ \App\Services\Cart\CartService::MAX_QUANTITY }}" inputmode="numeric"
                                   class="w-10 border-0 bg-transparent p-0 text-center text-[16px] [appearance:textfield] focus:ring-0 [&::-webkit-inner-spin-button]:appearance-none">
                            <button type="button" class="p-2.5" @click="qty = Math.min({{ $stock ?? \App\Services\Cart\CartService::MAX_QUANTITY }}, qty + 1)" aria-label="Arttır"><x-glyph name="plus" class="size-4" /></button>
                        </div>
                    </div>
                    @endif

                    <div class="mt-6 hidden gap-3 sm:grid sm:grid-cols-2">
                        <button type="submit" class="btn-outline">sepete ekle</button>
                        <button type="submit" formaction="{{ route('cart.buy-now') }}" class="btn-primary">hemen al</button>
                    </div>
                @endif

                @if ($isReserved)
                    <div class="mt-7">
                        <p class="rounded-2xl bg-paper-deep px-4 py-3 text-[15px] text-ink-soft">Bu parça şu an başka birinin sepetinde. Ödeme tamamlanmazsa kısa süre içinde yeniden satışa açılır.</p>
                        <button type="button" class="btn-outline mt-4 w-full cursor-not-allowed opacity-50" disabled>sepete ekle</button>
                    </div>
                @elseif ($isSold)
                    <div class="mt-7 rounded-2xl bg-paper-deep px-5 py-4">
                        <p class="text-[15px] text-ink-soft">Bu parça yeni evine gitti. Her parçamız tek; benzerini sizin için yapabiliriz.</p>
                        @if (session('similar_request_sent'))
                            <p class="mt-3 text-[15px] font-medium text-ink">Talebinizi aldık, en kısa sürede size döneceğiz.</p>
                        @else
                            <button type="button" class="btn-primary mt-3 inline-flex" x-data @click="$dispatch('open-similar-request')">benzerini iste</button>
                        @endif
                    </div>
                @endif

                @if ($whatsapp && ! $isSold)
                    <a href="https://wa.me/{{ $whatsapp }}?text={{ $waText }}" rel="noopener" class="mt-4 inline-flex items-center gap-2 text-[15px] text-ink-soft underline-offset-4 hover:text-ink hover:underline">
                        <x-glyph name="whatsapp" class="size-4" /> Sorunuz mu var? WhatsApp’tan yazın
                    </a>
                @endif

                <div class="mt-8 divide-y divide-line border-y border-line">
                    @if ($product->description)
                        <details class="group py-4" open>
                            <summary class="flex cursor-pointer list-none items-center justify-between font-hand text-xl">açıklama <x-glyph name="chevron-down" class="size-4 transition group-open:rotate-180" /></summary>
                            <div class="prose prose-neutral mt-3 max-w-none text-[15px] text-ink-soft prose-p:my-2">{!! $product->description !!}</div>
                        </details>
                    @endif
                    @if ($product->dimensions || $product->materials)
                        <details class="group py-4">
                            <summary class="flex cursor-pointer list-none items-center justify-between font-hand text-xl">ölçüler & malzeme <x-glyph name="chevron-down" class="size-4 transition group-open:rotate-180" /></summary>
                            <dl class="mt-3 grid grid-cols-[auto_1fr] gap-x-6 gap-y-2 text-[15px]">
                                @if ($product->dimensions)<dt class="text-ink-soft">Ölçüler</dt><dd>{{ $product->dimensions }}</dd>@endif
                                @if ($product->materials)<dt class="text-ink-soft">Malzeme</dt><dd>{{ $product->materials }}</dd>@endif
                            </dl>
                        </details>
                    @endif
                    @if ($product->care)
                        <details class="group py-4">
                            <summary class="flex cursor-pointer list-none items-center justify-between font-hand text-xl">bakım <x-glyph name="chevron-down" class="size-4 transition group-open:rotate-180" /></summary>
                            <p class="mt-3 whitespace-pre-line text-[15px] text-ink-soft">{{ $product->care }}</p>
                        </details>
                    @endif
                    @unless ($product->isWorkshop())
                        <details class="group py-4">
                            <summary class="flex cursor-pointer list-none items-center justify-between font-hand text-xl">kargo & iade <x-glyph name="chevron-down" class="size-4 transition group-open:rotate-180" /></summary>
                            <div class="mt-3 space-y-2 text-[15px] text-ink-soft">
                                <p class="flex gap-2"><x-glyph name="truck" class="mt-0.5 size-4 shrink-0" />{{ $settings['shipping_note'] }}</p>
                                @if (! empty($settings['free_shipping_threshold']))
                                    <p class="flex gap-2"><x-glyph name="gift" class="mt-0.5 size-4 shrink-0" />{{ Money::format((int) $settings['free_shipping_threshold']) }} ve üzeri siparişlerde kargo ücretsiz.</p>
                                @endif
                                <p class="flex gap-2"><x-glyph name="refresh" class="mt-0.5 size-4 shrink-0" />Teslimattan itibaren 14 gün içinde cayma hakkınızı kullanabilirsiniz (kişiye özel üretilen ürünler hariç).</p>
                            </div>
                        </details>
                    @endunless
                </div>
            </div>
        </div>

        {{-- Sticky buy bar for phones: the button is always under the thumb. --}}
        @if ($inStock)
            <div class="fixed inset-x-0 bottom-0 z-30 border-t border-line bg-paper/95 px-4 pb-[calc(env(safe-area-inset-bottom)+0.75rem)] pt-3 backdrop-blur sm:hidden">
                <div class="flex items-center gap-2">
                    <button type="submit" class="btn-outline flex-1 px-3">sepete ekle</button>
                    <button type="submit" formaction="{{ route('cart.buy-now') }}" class="btn-primary flex-[1.3] px-3">hemen al · {{ Money::format($product->price) }}</button>
                </div>
            </div>
        @endif
    </form>

    @if ($isSold)
        @include('shop.partials.similar-request', ['product' => $product])
    @endif

    @if ($related->isNotEmpty())
        <section class="container-shop pt-16">
            <x-shop.section-title title="bunlar da hoşunuza gidebilir" />
            <div class="grid grid-cols-2 gap-x-4 gap-y-8 sm:gap-x-6 lg:grid-cols-4">
                @foreach ($related as $item)
                    <x-shop.product-card :product="$item" />
                @endforeach
            </div>
        </section>
    @endif
@endsection
