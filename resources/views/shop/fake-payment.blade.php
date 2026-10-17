@extends('layouts.shop')
@use('App\Support\Money')

@section('title', 'Test ödemesi')
@section('noindex', '1')

@section('content')
    <div class="container-shop max-w-md pt-12">
        <div class="card p-6">
            <p class="badge bg-tile text-paper">TEST MODU</p>
            <h1 class="mt-3 text-3xl">sahte ödeme sayfası</h1>
            <p class="mt-2 text-sm text-ink-soft">PAYMENT_GATEWAY=fake olduğu için iyzico yerine bu sayfa gösteriliyor. Canlı ortamda asla kullanılmaz.</p>
            <p class="mt-4 text-lg">{{ $order->number }} · <strong>{{ Money::format($order->total) }}</strong></p>
            <form method="POST" action="{{ route('payment.fake.submit', $token) }}" class="mt-6 grid gap-2">
                @csrf
                <button name="result" value="success" class="btn-primary">Ödemeyi onayla</button>
                <button name="result" value="fail" class="btn-outline">Kart reddedildi</button>
            </form>
        </div>
    </div>
@endsection
