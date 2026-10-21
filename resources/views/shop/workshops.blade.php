@extends('layouts.shop')

@section('title', 'Atölyeler')
@section('description', 'Mozaik ve seramik atölyelerimize katılın: küçük gruplar, tüm malzemeler dahil.')

@section('content')
    {{-- Tesserae close-up behind the title, so the page opens on the material itself. --}}
    <div class="relative overflow-hidden bg-ink">
        <img src="{{ asset('images/site/tesserae.webp') }}" alt="" class="settle absolute inset-0 size-full object-cover opacity-55" fetchpriority="high">
        <div class="relative container-shop py-14 text-paper sm:py-20">
            <h1 class="text-6xl leading-none sm:text-7xl" data-reveal>atölyeler</h1>
        </div>
    </div>
    <div class="checker-edge" aria-hidden="true"></div>

    <div class="container-shop pt-8 sm:pt-12">
        <div class="grid items-end gap-6 sm:grid-cols-[1fr_auto]">
            <div>
                <p class="max-w-xl text-[17px] text-ink-soft" data-reveal>Küçük gruplar halinde, tüm malzemeler bizden. Daha önce hiç deneyiminiz olmasa da olur; yeter ki elleriniz biraz kirlensin.</p>
            </div>
            <x-doodle.sun class="spin-slow hidden size-28 sm:block" />
        </div>

        @if ($workshops->isEmpty())
            <p class="mt-12 rounded-2xl bg-paper-deep px-5 py-4 text-ink-soft">Şu anda planlanmış bir atölye yok. Yeni tarihleri Instagram’dan duyuruyoruz.</p>
        @else
            <div class="mt-10 grid gap-6 md:grid-cols-2 lg:grid-cols-3">
                @foreach ($workshops as $workshop)
                    @include('shop.partials.workshop-card', ['workshop' => $workshop])
                @endforeach
            </div>
        @endif
    </div>
@endsection
