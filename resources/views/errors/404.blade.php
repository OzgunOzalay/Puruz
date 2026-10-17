@extends('layouts.shop')

@section('title', 'Sayfa bulunamadı')

@section('content')
    <div class="container-shop flex flex-col items-center py-20 text-center">
        <x-doodle.sun class="size-36" />
        <h1 class="mt-6 text-5xl">burada bir pürüz var</h1>
        <p class="mt-3 max-w-md text-ink-soft">Aradığınız sayfa taşınmış, satıştan kalkmış ya da hiç var olmamış olabilir.</p>
        <a href="{{ route('shop.home') }}" class="btn-primary mt-8">ana sayfaya dön</a>
    </div>
@endsection
