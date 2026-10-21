@extends('layouts.shop')

@section('title', 'Bağlantılar')

@section('content')
    {{-- Link-in-bio page: put this URL in the Instagram profile instead of a third-party link tool. --}}
    <div class="mx-auto max-w-md px-4 pt-8">
        <div class="flex flex-col items-center text-center">
            <div class="relative">
                <img src="{{ asset('images/site/atolye.webp') }}" alt="" width="96" height="96" class="size-24 rounded-full object-cover object-top ring-2 ring-ink ring-offset-4 ring-offset-paper">
                <x-doodle.shards class="pointer-events-none absolute -right-7 -top-3 size-12" />
            </div>
            <p class="mt-4 font-hand text-2xl" data-reveal>{{ $settings['tagline'] }}</p>
        </div>

        <div class="mt-8 grid gap-3">
            <a href="{{ route('shop.index', ['utm_source' => 'instagram', 'utm_medium' => 'bio']) }}" class="btn-primary w-full py-4">tüm ürünler</a>
            <a href="{{ route('shop.workshops', ['utm_source' => 'instagram', 'utm_medium' => 'bio']) }}" class="btn-outline w-full py-4">atölye takvimi</a>
            @if (! empty($settings['whatsapp_number']))
                <a href="https://wa.me/{{ $settings['whatsapp_number'] }}" class="btn-outline w-full py-4" rel="noopener"><x-glyph name="whatsapp" class="size-4" /> WhatsApp’tan yazın</a>
            @endif
            <a href="{{ route('orders.lookup') }}" class="btn-ghost w-full">sipariş takibi</a>
        </div>

        @if ($sessions->isNotEmpty())
            <h2 class="mt-12 text-3xl">yaklaşan atölyeler</h2>
            <div class="mt-4 grid gap-2">
                @foreach ($sessions as $session)
                    <a href="{{ $session->product->url(['utm_source' => 'instagram', 'utm_medium' => 'bio']) }}" class="flex items-center justify-between gap-3 rounded-2xl border border-line bg-white/50 px-4 py-3">
                        <span>
                            <span class="block font-medium lowercase">{{ $session->product->name }}</span>
                            <span class="block text-sm text-ink-soft">{{ $session->starts_at->translatedFormat('j F l · H:i') }}</span>
                        </span>
                        <span class="text-sm {{ $session->seatsLeft() <= 2 ? 'text-tile' : 'text-ink-soft' }}">{{ $session->seatsLeft() > 0 ? $session->seatsLeft().' yer' : 'dolu' }}</span>
                    </a>
                @endforeach
            </div>
        @endif

        @if ($products->isNotEmpty())
            <h2 class="mt-12 text-3xl">öne çıkanlar</h2>
            <div class="mt-4 grid grid-cols-2 gap-x-4 gap-y-8">
                @foreach ($products as $product)
                    <x-shop.product-card :product="$product" />
                @endforeach
            </div>
        @endif

        <x-doodle.building class="mt-14" :address="$settings['address'] ?: 'İsmet İnönü Sok. No:8, Konak / İzmir'" />
    </div>
@endsection
