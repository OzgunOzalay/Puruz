@extends('layouts.shop')

@section('title', 'Atölyeler')
@section('description', 'Mozaik ve seramik atölyelerimize katılın: küçük gruplar, tüm malzemeler dahil.')

@section('content')
    <div class="container-shop pt-8 sm:pt-12">
        <div class="grid items-end gap-6 sm:grid-cols-[1fr_auto]">
            <div>
                <h1 class="text-6xl leading-none sm:text-7xl">atölyeler</h1>
                <p class="mt-4 max-w-xl text-[17px] text-ink-soft">Küçük gruplar halinde, tüm malzemeler bizden. Daha önce hiç deneyiminiz olmasa da olur; yeter ki elleriniz biraz kirlensin.</p>
            </div>
            <x-doodle.sun class="hidden size-36 sm:block" />
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
