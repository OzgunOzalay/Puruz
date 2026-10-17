@extends('layouts.shop')

@section('content')
    @php
        $heroImage = ! empty($settings['hero_image'])
            ? \App\Support\ProductImages::url($settings['hero_image'], 1600)
            : ($workshops->first()?->coverUrl(1600) ?? $featured->first()?->coverUrl(1600));
    @endphp

    {{-- Hero, laid out like the workshop posters: big lowercase marker title, photo, doodle. --}}
    <section class="container-shop grid items-center gap-8 pb-16 pt-8 sm:pt-12 lg:grid-cols-[1.1fr_1fr] lg:gap-12">
        <div class="relative">
            <h1 class="text-[2.75rem] leading-[1.02] sm:text-6xl lg:text-7xl">{{ $settings['hero_title'] }}</h1>
            <p class="mt-5 max-w-md text-[17px] leading-relaxed text-ink-soft">{{ $settings['hero_text'] }}</p>
            <div class="mt-7 flex flex-wrap gap-3">
                <a href="{{ route('shop.index') }}" class="btn-primary">ürünlere bak <x-glyph name="arrow-right" class="size-4" /></a>
                <a href="{{ route('shop.workshops') }}" class="btn-outline">atölyelere katıl</a>
            </div>
        </div>
        <div class="relative">
            @if ($heroImage)
                <img src="{{ $heroImage }}" alt="" class="aspect-[4/5] w-full rounded-[2rem] object-cover sm:aspect-[5/4] lg:aspect-[4/5]" fetchpriority="high">
            @else
                <div class="aspect-[5/4] rounded-[2rem] bg-paper-deep"></div>
            @endif
            <x-doodle.sun class="pointer-events-none absolute -bottom-10 -right-4 size-36 text-ink sm:-right-10 sm:size-48" />
        </div>
    </section>

    @if ($categories->isNotEmpty())
        <section class="container-shop pb-14">
            <div class="-mx-4 flex snap-x gap-3 overflow-x-auto px-4 pb-2 scrollbar-none sm:mx-0 sm:flex-wrap sm:px-0">
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

    @if ($workshops->isNotEmpty())
        <section class="bg-paper-deep/70 py-16 sm:py-20">
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

    <section class="container-shop">
        <div class="grid gap-6 rounded-[2rem] border-[1.5px] border-ink p-8 sm:grid-cols-[1fr_auto] sm:items-center sm:p-12">
            <div>
                <p class="font-hand text-3xl sm:text-4xl">her parça elde, tek tek yapılıyor</p>
                <p class="mt-3 max-w-xl text-ink-soft">Sır renkleri ve formlar her fırınlamada biraz farklı çıkar; o küçük pürüzler her parçayı kendine özel yapar. Yeni ürünleri ilk görmek için bizi Instagram’da takip edin.</p>
            </div>
            @if (! empty($settings['instagram_handle']))
                <a href="https://instagram.com/{{ $settings['instagram_handle'] }}" class="btn-primary" rel="noopener">
                    <x-glyph name="instagram" class="size-4" /> {{ '@'.$settings['instagram_handle'] }}
                </a>
            @endif
        </div>
    </section>
@endsection
