<x-filament-widgets::widget>
    <x-filament::section heading="Bekleyenler">
        @forelse (\App\Filament\Widgets\Waiting::items() as $item)
            <a href="{{ $item['url'] }}" class="flex items-center justify-between gap-3 border-b border-gray-100 py-2.5 text-sm last:border-0 hover:bg-gray-50 dark:border-white/5 dark:hover:bg-white/5">
                <span>
                    <span class="font-medium text-gray-950 dark:text-white">{{ $item['title'] }}</span>
                    <span class="block text-gray-500 dark:text-gray-400">{{ $item['meta'] }} · {{ \App\Support\Human::date($item['since']) }}</span>
                </span>
                @if ($item['action'])<span class="shrink-0 text-primary-600 dark:text-primary-400">{{ $item['action'] }} →</span>@endif
            </a>
        @empty
            <p class="text-sm text-gray-500">Bekleyen iş yok.</p>
        @endforelse
    </x-filament::section>
</x-filament-widgets::widget>
