@php
    $shopName = $settings['shop_name'] ?? config('app.name');
    $pageTitle = trim($__env->yieldContent('title'));
    $fullTitle = $pageTitle ? $pageTitle.' · '.$shopName : $shopName.' · '.($settings['tagline'] ?? '');
    $description = trim($__env->yieldContent('description')) ?: ($settings['hero_text'] ?? '');
    $instagram = $settings['instagram_handle'] ?? null;
    $whatsapp = $settings['whatsapp_number'] ?? null;
    // The workshops link is always in the menu, so a category with the same name would be a duplicate.
    $menuCategories = $navCategories->reject(fn ($c) => \Illuminate\Support\Str::slug($c->name) === 'atolyeler');
@endphp
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <title>{{ $fullTitle }}</title>
    <meta name="description" content="{{ \Illuminate\Support\Str::limit(strip_tags($description), 160) }}">
    <link rel="canonical" href="{{ trim($__env->yieldContent('canonical')) ?: url()->current() }}">
    <meta name="theme-color" content="#f5f4ef">
    @hasSection('noindex')
        <meta name="robots" content="noindex, nofollow">
    @endif

    {{-- Link previews in Instagram DMs, WhatsApp and stories are built from these tags. --}}
    <meta property="og:site_name" content="{{ $shopName }}">
    <meta property="og:locale" content="tr_TR">
    <meta property="og:title" content="{{ $pageTitle ?: $shopName }}">
    <meta property="og:description" content="{{ \Illuminate\Support\Str::limit(strip_tags($description), 200) }}">
    <meta property="og:url" content="{{ url()->current() }}">
    @hasSection('og')
        @yield('og')
    @else
        <meta property="og:type" content="website">
        <meta property="og:image" content="{{ asset('images/brand/og-default.png') }}">
        <meta property="og:image:width" content="1200">
        <meta property="og:image:height" content="630">
    @endif
    <meta name="twitter:card" content="summary_large_image">

    <link rel="icon" href="{{ asset('favicon.ico') }}" sizes="48x48">
    <link rel="icon" href="{{ asset('favicon.svg') }}" type="image/svg+xml">
    <link rel="apple-touch-icon" href="{{ asset('apple-touch-icon.png') }}">

    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @stack('head')
</head>
<body class="min-h-dvh">
    <a href="#icerik" class="sr-only focus:not-sr-only focus:absolute focus:left-4 focus:top-4 focus:z-50 focus:rounded-full focus:bg-ink focus:px-4 focus:py-2 focus:text-paper">İçeriğe geç</a>

    @if (! empty($settings['store_closed']))
        <div class="bg-tile px-4 py-2 text-center text-sm text-paper">{{ $settings['store_closed_message'] }}</div>
    @elseif (! empty($settings['announcement']))
        <div class="bg-ink px-4 py-2 text-center text-[13px] text-paper">
            @if (! empty($settings['announcement_link']))
                <a href="{{ $settings['announcement_link'] }}" class="underline-offset-4 hover:underline">{{ $settings['announcement'] }}</a>
            @else
                {{ $settings['announcement'] }}
            @endif
        </div>
    @endif

    <header x-data="{ menu: false, search: false }" class="sticky top-0 z-40 border-b border-line/80 bg-paper/90 backdrop-blur supports-[backdrop-filter]:bg-paper/80">
        <div class="container-shop grid h-16 grid-cols-[1fr_auto_1fr] items-center gap-3">
            <div class="flex items-center gap-1">
                <button type="button" class="-ml-2 rounded-full p-2 lg:hidden" @click="menu = !menu" :aria-expanded="menu" aria-label="Menü">
                    <x-glyph name="menu" x-show="!menu" />
                    <x-glyph name="close" x-show="menu" x-cloak />
                </button>
                <nav class="hidden items-center gap-5 whitespace-nowrap text-[15px] lg:flex">
                    <a href="{{ route('shop.index') }}" class="hover:text-ink-soft">ürünler</a>
                    @foreach ($menuCategories->take(3) as $navCategory)
                        <a href="{{ $navCategory->url() }}" class="lowercase hover:text-ink-soft">{{ $navCategory->name }}</a>
                    @endforeach
                    <a href="{{ route('shop.workshops') }}" class="hover:text-ink-soft">atölyeler</a>
                </nav>
            </div>

            <a href="{{ route('shop.home') }}" class="shrink-0" aria-label="{{ $shopName }} ana sayfa">
                <x-logo class="h-8 w-auto sm:h-9" />
            </a>

            <div class="flex items-center justify-end gap-1">
                <button type="button" class="rounded-full p-2" @click="search = !search; $nextTick(() => $refs.q?.focus())" aria-label="Ara">
                    <x-glyph name="search" />
                </button>
                <a href="{{ route('cart.show') }}" class="relative -mr-2 rounded-full p-2" aria-label="Sepet ({{ $cartCount }})">
                    <x-glyph name="bag" />
                    @if ($cartCount > 0)
                        <span class="absolute right-0.5 top-0.5 flex size-[18px] items-center justify-center rounded-full bg-cobalt text-[11px] font-semibold text-paper">{{ $cartCount }}</span>
                    @endif
                </a>
            </div>
        </div>

        <div x-show="search" x-collapse x-cloak class="border-t border-line/80">
            <form action="{{ route('shop.index') }}" class="container-shop flex items-center gap-2 py-3">
                <input x-ref="q" type="search" name="q" value="{{ request('q') }}" placeholder="kupa, tabak, vazo…" class="input" enterkeyhint="search">
                <button class="btn-primary px-4" aria-label="Ara"><x-glyph name="search" /></button>
            </form>
        </div>

        <div x-show="menu" x-collapse x-cloak class="border-t border-line/80 lg:hidden">
            <nav class="container-shop flex flex-col py-3 font-hand text-2xl">
                <a href="{{ route('shop.index') }}" class="py-2">tüm ürünler</a>
                @foreach ($menuCategories as $navCategory)
                    <a href="{{ $navCategory->url() }}" class="py-2 lowercase">{{ $navCategory->name }}</a>
                @endforeach
                <a href="{{ route('shop.workshops') }}" class="py-2">atölyeler</a>
                <a href="{{ route('orders.lookup') }}" class="py-2 text-ink-soft">sipariş takibi</a>
            </nav>
        </div>
    </header>

    @if (session('status') || session('error'))
        <div x-data="{ show: true }" x-show="show" x-init="setTimeout(() => show = false, 6000)" x-transition.opacity
             class="fixed inset-x-4 top-20 z-50 mx-auto max-w-md">
            <div @class([
                'flex items-start gap-3 rounded-2xl px-4 py-3 text-[15px] shadow-lg',
                'bg-ink text-paper' => session('status'),
                'bg-tile text-paper' => session('error'),
            ]) role="status">
                <x-glyph :name="session('error') ? 'alert' : 'check'" class="mt-0.5 size-5 shrink-0" />
                <p class="flex-1">{{ session('error') ?? session('status') }}</p>
                <button type="button" @click="show = false" aria-label="Kapat"><x-glyph name="close" class="size-4" /></button>
            </div>
        </div>
    @endif

    <main id="icerik">
        @yield('content')
    </main>

    @if (session('cart_added'))
        @include('shop.partials.cart-drawer')
    @endif

    <footer class="mt-24 border-t border-line pb-[calc(env(safe-area-inset-bottom)+6rem)] pt-14 lg:pb-12">
        <div class="container-shop grid gap-10 md:grid-cols-[1.3fr_1fr_1fr]">
            <div class="flex items-start gap-5">
                <x-doodle.sun class="size-24 shrink-0 text-ink" />
                <div>
                    <p class="font-hand text-3xl leading-tight">bize ulaşın</p>
                    <ul class="mt-3 space-y-2 text-[15px] text-ink-soft">
                        @if (! empty($settings['contact_phone']))
                            <li><a class="inline-flex items-center gap-2 hover:text-ink" href="tel:{{ preg_replace('/\s+/', '', $settings['contact_phone']) }}"><x-glyph name="phone" class="size-4" />{{ $settings['contact_phone'] }}</a></li>
                        @endif
                        @if ($whatsapp)
                            <li><a class="inline-flex items-center gap-2 hover:text-ink" href="https://wa.me/{{ $whatsapp }}" rel="noopener"><x-glyph name="whatsapp" class="size-4" />WhatsApp</a></li>
                        @endif
                        @if ($instagram)
                            <li><a class="inline-flex items-center gap-2 hover:text-ink" href="https://instagram.com/{{ $instagram }}" rel="noopener"><x-glyph name="instagram" class="size-4" />{{ '@'.$instagram }}</a></li>
                        @endif
                        @if (! empty($settings['contact_email']))
                            <li><a class="inline-flex items-center gap-2 hover:text-ink" href="mailto:{{ $settings['contact_email'] }}"><x-glyph name="mail" class="size-4" />{{ $settings['contact_email'] }}</a></li>
                        @endif
                        @if (! empty($settings['address']))
                            <li class="flex items-start gap-2"><x-glyph name="pin" class="mt-0.5 size-4 shrink-0" />{{ $settings['address'] }}</li>
                        @endif
                    </ul>
                </div>
            </div>
            <div>
                <p class="font-hand text-xl">alışveriş</p>
                <ul class="mt-3 space-y-2 text-[15px] text-ink-soft">
                    <li><a class="hover:text-ink" href="{{ route('shop.index') }}">Tüm ürünler</a></li>
                    <li><a class="hover:text-ink" href="{{ route('shop.workshops') }}">Atölyeler</a></li>
                    <li><a class="hover:text-ink" href="{{ route('orders.lookup') }}">Sipariş takibi</a></li>
                    <li><a class="hover:text-ink" href="{{ route('cart.show') }}">Sepetim</a></li>
                </ul>
            </div>
            <div>
                <p class="font-hand text-xl">bilgi</p>
                <ul class="mt-3 space-y-2 text-[15px] text-ink-soft">
                    @foreach ($footerPages as $footerPage)
                        <li><a class="hover:text-ink" href="{{ $footerPage->url() }}">{{ $footerPage->title }}</a></li>
                    @endforeach
                </ul>
            </div>
        </div>
        <div class="container-shop mt-12 flex flex-col items-start justify-between gap-4 border-t border-line pt-6 text-[13px] text-ink-faint sm:flex-row sm:items-center">
            <p>© {{ now()->year }} {{ $settings['legal_name'] ?: $shopName }}. Tüm hakları saklıdır.</p>
            {{-- iyzico asks merchants to show its logo band; replace with the official image from the iyzico merchant panel. --}}
            <div class="flex items-center gap-2 rounded-full border border-line px-3 py-1.5 text-ink-soft">
                <x-glyph name="lock" class="size-3.5" />
                <span>iyzico ile güvenli ödeme</span>
                <span class="text-ink-faint">· Visa · Mastercard · Troy</span>
            </div>
        </div>
    </footer>

    @stack('scripts')
</body>
</html>
