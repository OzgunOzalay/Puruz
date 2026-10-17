@extends('layouts.shop')

@section('title', $search ? '“'.$search.'” araması' : $title)
@section('description', $category?->seo_description ?: $category?->description ?: 'El yapımı seramik kupalar, tabaklar, vazolar ve atölyeler.')

@section('content')
    <div class="container-shop pt-8 sm:pt-12">
        <h1 class="text-5xl lowercase leading-none sm:text-6xl">{{ $search ? '“'.$search.'”' : $title }}</h1>
        @if ($category?->description)
            <p class="mt-4 max-w-2xl text-[17px] text-ink-soft">{{ $category->description }}</p>
        @endif

        <div class="-mx-4 mt-7 flex gap-2 overflow-x-auto px-4 pb-1 scrollbar-none sm:mx-0 sm:flex-wrap sm:px-0">
            <a href="{{ route('shop.index') }}" @class(['chip shrink-0', 'border-ink bg-ink !text-paper' => ! $category])>tümü</a>
            @foreach ($categories as $item)
                <a href="{{ $item->url() }}" @class(['chip shrink-0 lowercase', 'border-ink bg-ink !text-paper' => $category?->is($item)])>{{ $item->name }}</a>
            @endforeach
        </div>

        <form class="mt-5 flex flex-wrap items-center justify-between gap-3 border-b border-line pb-4 text-sm" x-data @change="$el.submit()">
            @if ($search)<input type="hidden" name="q" value="{{ $search }}">@endif
            <label class="inline-flex items-center gap-2 text-ink-soft">
                <input type="checkbox" name="stokta" value="1" @checked(request()->boolean('stokta')) class="size-4 rounded border-line text-ink focus:ring-ink">
                sadece satıştakiler
            </label>
            <label class="inline-flex items-center gap-2 text-ink-soft">
                sırala
                <select name="sirala" class="rounded-full border-line bg-transparent py-1.5 pl-3 pr-8 text-sm focus:border-ink focus:ring-0">
                    <option value="">önerilen</option>
                    <option value="yeni" @selected(request('sirala') === 'yeni')>en yeni</option>
                    <option value="fiyat-artan" @selected(request('sirala') === 'fiyat-artan')>fiyat: düşükten yükseğe</option>
                    <option value="fiyat-azalan" @selected(request('sirala') === 'fiyat-azalan')>fiyat: yüksekten düşüğe</option>
                </select>
            </label>
        </form>

        @if ($products->isEmpty())
            <div class="flex flex-col items-center py-24 text-center">
                <x-doodle.sun class="size-32 text-ink-faint" />
                <p class="mt-6 font-hand text-2xl">burada henüz bir şey yok</p>
                <p class="mt-2 text-ink-soft">Yeni parçalar fırından çıkınca burada olacak.</p>
                <a href="{{ route('shop.index') }}" class="btn-outline mt-6">tüm ürünler</a>
            </div>
        @else
            <div class="mt-8 grid grid-cols-2 gap-x-4 gap-y-10 sm:gap-x-6 lg:grid-cols-4">
                @foreach ($products as $product)
                    <x-shop.product-card :product="$product" />
                @endforeach
            </div>
            <div class="mt-12">{{ $products->links() }}</div>
        @endif
    </div>
@endsection
