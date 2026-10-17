@extends('layouts.shop')
@use('App\Support\Money')

@section('title', 'Ödeme tamamlanamadı')
@section('noindex', '1')

@section('content')
    <div class="container-shop max-w-xl pt-12 text-center">
        <x-doodle.sun class="mx-auto size-28 text-ink-faint" />
        <h1 class="mt-6 text-4xl">ödeme tamamlanamadı</h1>
        <p class="mt-3 text-ink-soft">{{ session('error') ?? $order->payment_error ?? 'Kartınızdan herhangi bir ücret çekilmedi.' }}</p>
        <p class="mt-1 text-sm text-ink-faint">Sipariş no: {{ $order->number }} · {{ Money::format($order->total) }}</p>

        <form method="POST" action="{{ route('payment.retry', $order->tracking_token) }}" class="mt-8">
            @csrf
            <button class="btn-primary"><x-glyph name="refresh" class="size-4" /> tekrar dene</button>
        </form>
        <a href="{{ route('cart.show') }}" class="btn-ghost mt-3">sepete dön</a>

        <p class="mt-10 text-sm text-ink-soft">Sorun devam ederse farklı bir kart deneyebilir ya da bize
            <a href="https://wa.me/{{ $settings['whatsapp_number'] }}" class="underline">WhatsApp’tan</a> yazabilirsiniz.</p>
    </div>
@endsection
