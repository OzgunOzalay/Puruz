@extends('layouts.shop')

@section('title', 'Sipariş takibi')

@section('content')
    <div class="container-shop max-w-md pt-12">
        <h1 class="text-5xl leading-none">sipariş takibi</h1>
        <p class="mt-3 text-ink-soft">Takip bağlantısı sipariş onay e-postanızda. Bulamadıysanız sipariş numaranızı ve e-posta adresinizi yazın, bağlantıyı tekrar gönderelim.</p>
        <form method="POST" action="{{ route('orders.lookup.send') }}" class="mt-8 space-y-4">
            @csrf
            <div>
                <label for="number" class="label">Sipariş numarası</label>
                <input id="number" name="number" value="{{ old('number') }}" placeholder="PRZ-26-000123" class="input uppercase" required>
                @error('number')<p class="field-error">{{ $message }}</p>@enderror
            </div>
            <div>
                <label for="email" class="label">E-posta</label>
                <input id="email" type="email" name="email" value="{{ old('email') }}" class="input" required>
                @error('email')<p class="field-error">{{ $message }}</p>@enderror
            </div>
            <button class="btn-primary w-full">bağlantıyı gönder</button>
        </form>
    </div>
@endsection
