<x-filament-widgets::widget>
    <x-filament::section heading="Son satışlar">
        <div class="grid gap-3 sm:grid-cols-5">
            @forelse (\App\Filament\Widgets\RecentSales::pieces() as $piece)
                <a href="{{ \App\Filament\Resources\Products\ProductResource::getUrl('edit', ['record' => $piece]) }}" class="text-sm">
                    @if ($cover = $piece->coverImage())
                        <img src="{{ \App\Support\ProductImages::url($cover, 400) }}" alt="" class="aspect-square w-full rounded-lg object-cover">
                    @endif
                    <span class="mt-1.5 block font-medium text-gray-950 dark:text-white">{{ $piece->name }}</span>
                    <span class="block text-gray-500 dark:text-gray-400">{{ \App\Support\Money::format($piece->price) }} · {{ \App\Support\Human::day($piece->sold_at) }} · {{ \App\Support\Human::took($piece->published_at ?? $piece->created_at, $piece->sold_at) }}</span>
                </a>
            @empty
                <p class="text-sm text-gray-500">Henüz satış yok.</p>
            @endforelse
        </div>
        @if ($line = \App\Filament\Widgets\RecentSales::sourceLine())
            <p class="mt-4 text-sm text-gray-500 dark:text-gray-400">{{ $line }}</p>
        @endif
    </x-filament::section>
</x-filament-widgets::widget>
