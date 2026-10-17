@extends('layouts.shop')
@use('App\Support\Money')

@section('title', 'Sepetim')
@section('noindex', '1')

@section('content')
    <div class="container-shop pt-8 sm:pt-12">
        <h1 class="text-5xl leading-none">sepetim</h1>

        @if ($cart->isEmpty())
            <div class="flex flex-col items-center py-20 text-center">
                <x-doodle.sun class="size-32 text-ink-faint" />
                <p class="mt-6 font-hand text-2xl">sepetiniz boş</p>
                <a href="{{ route('shop.index') }}" class="btn-primary mt-6">alışverişe başla</a>
            </div>
        @else
            <div class="mt-8 grid gap-10 lg:grid-cols-[1fr_380px]">
                <ul class="divide-y divide-line border-y border-line">
                    @foreach ($cart->lines() as $line)
                        <li class="flex gap-4 py-5">
                            <a href="{{ $line->product->url() }}" class="shrink-0">
                                <img src="{{ $line->product->coverUrl(400) }}" alt="" class="size-24 rounded-2xl bg-paper-deep object-cover sm:size-28">
                            </a>
                            <div class="flex min-w-0 flex-1 flex-col">
                                <div class="flex justify-between gap-3">
                                    <div>
                                        <a href="{{ $line->product->url() }}" class="font-medium">{{ $line->product->name }}</a>
                                        @if ($line->description())<p class="text-sm text-ink-soft">{{ $line->description() }}</p>@endif
                                        @if ($line->problem)<p class="mt-1 text-sm text-tile">{{ $line->problem }}</p>@endif
                                    </div>
                                    <p class="shrink-0 font-medium">{{ Money::format($line->total()) }}</p>
                                </div>
                                <div class="mt-auto flex items-center justify-between pt-3">
                                    <form method="POST" action="{{ route('cart.update', $line->key) }}" class="flex items-center rounded-full border border-line" x-data>
                                        @csrf @method('PATCH')
                                        <button name="quantity" value="{{ $line->quantity - 1 }}" class="p-2" aria-label="Azalt"><x-glyph name="minus" class="size-4" /></button>
                                        <span class="w-8 text-center text-[15px]">{{ $line->quantity }}</span>
                                        <button name="quantity" value="{{ $line->quantity + 1 }}" class="p-2 disabled:opacity-30" aria-label="Arttır" @disabled($line->maxQuantity() !== null && $line->quantity >= $line->maxQuantity())><x-glyph name="plus" class="size-4" /></button>
                                    </form>
                                    <form method="POST" action="{{ route('cart.remove', $line->key) }}">
                                        @csrf @method('DELETE')
                                        <button class="inline-flex items-center gap-1 text-sm text-ink-soft hover:text-tile"><x-glyph name="trash" class="size-4" /> kaldır</button>
                                    </form>
                                </div>
                            </div>
                        </li>
                    @endforeach
                </ul>

                <aside class="space-y-5 lg:sticky lg:top-24 lg:self-start">
                    <div class="card p-5">
                        @if ($cart->coupon())
                            <form method="POST" action="{{ route('cart.coupon.remove') }}" class="mb-4 flex items-center justify-between rounded-xl bg-cobalt-soft px-3 py-2 text-sm text-cobalt">
                                @csrf @method('DELETE')
                                <span>Kupon: <strong>{{ $cart->coupon()->code }}</strong></span>
                                <button class="underline">kaldır</button>
                            </form>
                        @else
                            <form method="POST" action="{{ route('cart.coupon') }}" class="mb-4">
                                @csrf
                                <label for="code" class="label">İndirim kodu</label>
                                <div class="flex gap-2">
                                    <input id="code" name="code" value="{{ old('code') }}" class="input py-2.5 uppercase" autocapitalize="characters">
                                    <button class="btn-outline px-4 py-2.5">uygula</button>
                                </div>
                                @error('code')<p class="field-error">{{ $message }}</p>@enderror
                            </form>
                        @endif

                        @include('shop.partials.summary')

                        @if ($gap = $cart->freeShippingGap())
                            <p class="mt-4 rounded-xl bg-paper-deep px-3 py-2 text-sm text-ink-soft">Ücretsiz kargo için {{ Money::format($gap) }} daha ekleyin.</p>
                        @endif

                        <a href="{{ route('checkout.show') }}" @class(['btn-primary mt-5 w-full', 'pointer-events-none opacity-50' => $cart->hasSoldOutLines()])>ödemeye geç <x-glyph name="arrow-right" class="size-4" /></a>
                    </div>
                    <p class="flex items-center justify-center gap-2 text-sm text-ink-soft"><x-glyph name="lock" class="size-4" /> Ödemeler iyzico güvencesiyle, 3D Secure ile alınır.</p>
                </aside>
            </div>
        @endif
    </div>
@endsection
