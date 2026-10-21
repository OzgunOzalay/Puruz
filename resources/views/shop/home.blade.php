@extends('layouts.shop')

@section('content')
    @php
        $heroImage = ! empty($settings['hero_image'])
            ? \App\Support\ProductImages::url($settings['hero_image'], 1600)
            : asset('images/site/ayna.webp');
        $address = $settings['address'] ?: 'İsmet İnönü Sok. No:8, Konak / İzmir';
        $instagram = $settings['instagram_handle'] ?? null;
    @endphp

    {{-- Hero: on phones the photo comes first, since most visitors arrive from an Instagram ad. --}}
    <section class="container-shop grid items-center gap-7 pb-14 pt-5 sm:pt-12 lg:grid-cols-[1.1fr_1fr] lg:gap-12 lg:pb-20">
        <div class="relative order-first lg:order-last">
            <div class="shard-hero overflow-hidden bg-paper-deep">
                <img src="{{ $heroImage }}" alt="" class="settle aspect-square w-full object-cover sm:aspect-[5/4] lg:aspect-[4/5]" fetchpriority="high">
            </div>
            <x-doodle.shards class="pointer-events-none absolute -left-3 -top-5 size-20 sm:-left-8 sm:size-28" />
            <x-doodle.shards class="pointer-events-none absolute -bottom-6 -right-2 size-16 rotate-90 sm:-right-6 sm:size-24" />
        </div>
        <div>
            <h1 class="text-[2.6rem] leading-[1.04] sm:text-6xl lg:text-7xl">
                @foreach (preg_split('/\s+/u', trim($settings['hero_title'])) as $i => $word)
                    @if (str_contains(mb_strtolower($word), 'pürüz'))
                        <span class="relative inline-block" data-reveal style="--i: {{ $i }}">{{ $word }}<svg class="draw-line absolute -bottom-1 left-0 h-3 w-full text-tile" viewBox="0 0 200 12" preserveAspectRatio="none" aria-hidden="true"><path pathLength="1" d="M3 8C40 3 80 10 120 6S175 3 197 7" fill="none" stroke="currentColor" stroke-width="3.2" stroke-linecap="round" /></svg></span>
                    @else
                        <span class="inline-block" data-reveal style="--i: {{ $i }}">{{ $word }}</span>
                    @endif
                @endforeach
            </h1>
            <p class="mt-5 max-w-md text-[17px] leading-relaxed text-ink-soft" data-reveal style="--i: 6">{{ $settings['hero_text'] }}</p>
            <div class="mt-7 flex flex-wrap gap-3" data-reveal style="--i: 7">
                <a href="{{ route('shop.index') }}" class="btn-primary">ürünlere bak <x-glyph name="arrow-right" class="size-4" /></a>
                <a href="{{ route('shop.workshops') }}" class="btn-outline">atölyelere katıl</a>
            </div>
        </div>
    </section>

    @if ($categories->isNotEmpty())
        <section class="container-shop pb-14">
            <div data-reveal class="fade-edge-r -mx-4 flex snap-x gap-3 overflow-x-auto px-4 pb-2 scrollbar-none sm:mx-0 sm:flex-wrap sm:px-0 sm:[mask-image:none] sm:[-webkit-mask-image:none]">
                @foreach ($categories as $category)
                    <a href="{{ $category->url() }}" class="flex shrink-0 snap-start items-center gap-3 rounded-full border border-ink/15 bg-white/50 py-1.5 pl-1.5 pr-5 hover:border-ink">
                        @if ($category->imageUrl())
                            <img src="{{ $category->imageUrl() }}" alt="" class="size-10 rounded-full object-cover">
                        @else
                            <span class="flex size-10 items-center justify-center rounded-full bg-paper-deep"><x-glyph name="sparkle" class="size-4 text-ink-soft" /></span>
                        @endif
                        <span class="font-hand text-lg lowercase">{{ $category->name }}</span>
                    </a>
                @endforeach
            </div>
        </section>
    @endif

    @if ($featured->isNotEmpty())
        <section class="container-shop pb-20">
            <x-shop.section-title title="atölyeden seçtiklerimiz" :link="route('shop.index')" />
            <div class="grid grid-cols-2 gap-x-4 gap-y-8 sm:gap-x-6 lg:grid-cols-4">
                @foreach ($featured as $product)
                    <x-shop.product-card :product="$product" />
                @endforeach
            </div>
        </section>
    @endif

    {{-- The workshop story: who makes the pieces and where. --}}
    <section class="bg-plaster/70 py-16 sm:py-24">
        <div class="container-shop grid items-center gap-12 lg:grid-cols-2 lg:gap-16">
            <div class="relative mx-auto w-full max-w-md lg:max-w-none" data-reveal>
                <img src="{{ asset('images/site/atolye.webp') }}" alt="Pürüz Atölye'de çalışma masası" width="900" height="1200" loading="lazy" decoding="async"
                     class="fade-in shard-2 aspect-[4/5] w-[82%] object-cover">
                <img src="{{ asset('images/site/tesserae.webp') }}" alt="Taş ve kiremit mozaik parçalarına yakından bakış" width="900" height="1200" loading="lazy" decoding="async"
                     class="fade-in shard-3 absolute -bottom-6 right-0 aspect-square w-[46%] object-cover ring-[6px] ring-plaster" data-reveal>
                <x-doodle.shards class="pointer-events-none absolute -right-1 top-6 size-16 sm:size-20" />
            </div>
            <div>
                <x-shop.section-title title="atölyeden" />
                <p class="text-[17px] leading-relaxed text-ink-soft" data-reveal>Pürüz, İzmir Konak’ta küçük bir atölye. Kırık çinileri, taşları ve elde şekillenen kili bir araya getirip her biri tek olan parçalar yapıyoruz.</p>
                <p class="mt-4 font-hand text-2xl leading-snug sm:text-3xl" data-reveal>her parça elde, tek tek yapılıyor</p>
                <p class="mt-3 text-[17px] leading-relaxed text-ink-soft" data-reveal>Sır renkleri ve formlar her fırınlamada biraz farklı çıkar; o küçük pürüzler her parçayı kendine özel yapar.</p>
                <ul class="mt-6 flex flex-wrap gap-2" data-reveal>
                    <li class="chip border-ink/30 bg-paper/70">tek parça</li>
                    <li class="chip border-ink/30 bg-paper/70">elde dizildi</li>
                    <li class="chip border-ink/30 bg-paper/70">izmir’de yapıldı</li>
                </ul>
                @if ($instagram)
                    <div class="mt-8" data-reveal>
                        <a href="https://instagram.com/{{ $instagram }}" class="btn-primary" rel="noopener">
                            <x-glyph name="instagram" class="size-4" /> {{ '@'.$instagram }}
                        </a>
                    </div>
                @endif
            </div>
        </div>
    </section>

    @if ($workshops->isNotEmpty())
        <section class="bg-mint/45 py-16 sm:py-20">
            <div class="container-shop">
                <x-shop.section-title title="atölyeler" :link="route('shop.workshops')" link-text="tüm atölyeler" />
                <div class="grid gap-6 md:grid-cols-2 lg:grid-cols-3">
                    @foreach ($workshops as $workshop)
                        @include('shop.partials.workshop-card', ['workshop' => $workshop])
                    @endforeach
                </div>
            </div>
        </section>
    @endif

    @if ($latest->isNotEmpty())
        <section class="container-shop py-20">
            <x-shop.section-title title="yeni çıkanlar" :link="route('shop.index', ['sirala' => 'yeni'])" />
            <div class="grid grid-cols-2 gap-x-4 gap-y-8 sm:gap-x-6 lg:grid-cols-4">
                @foreach ($latest as $product)
                    <x-shop.product-card :product="$product" />
                @endforeach
            </div>
        </section>
    @endif

    {{-- Visit us: the building from the opening poster, drawn in as it scrolls into view. --}}
    <section class="container-shop pt-4 text-center">
        <h2 class="text-3xl sm:text-4xl" data-reveal>bizi ziyaret edin</h2>
        <x-doodle.building class="mt-8" :address="$address" />
        <div class="mt-6 flex flex-wrap justify-center gap-3" data-reveal>
            <a href="https://www.google.com/maps/search/?api=1&query={{ urlencode($address) }}" class="btn-outline" rel="noopener"><x-glyph name="pin" class="size-4" /> yol tarifi</a>
            <a href="{{ route('shop.workshops') }}" class="btn-ghost">atölye takvimi <x-glyph name="arrow-right" class="size-4" /></a>
        </div>
    </section>
@endsection
