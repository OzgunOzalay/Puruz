<x-filament-widgets::widget>
    <x-filament::section heading="Yaklaşan atölyeler">
        @forelse (\App\Filament\Widgets\UpcomingWorkshops::sessions() as $session)
            <a href="{{ \App\Filament\Resources\WorkshopSessions\WorkshopSessionResource::getUrl() }}" class="flex items-center justify-between gap-3 border-b border-gray-100 py-2.5 text-sm last:border-0 hover:bg-gray-50 dark:border-white/5 dark:hover:bg-white/5">
                <span>
                    <span class="font-medium text-gray-950 dark:text-white">{{ $session->starts_at->translatedFormat('j M D H:i') }}</span>
                    <span class="block text-gray-500 dark:text-gray-400">{{ $session->product->name }}</span>
                </span>
                <span @class(['shrink-0', 'text-danger-600' => $session->seatsLeft() === 0, 'text-gray-500 dark:text-gray-400' => $session->seatsLeft() > 0])>{{ $session->seats_reserved }}/{{ $session->capacity }} dolu</span>
            </a>
        @empty
            <p class="text-sm text-gray-500">Planlanmış atölye yok.</p>
        @endforelse
    </x-filament::section>
</x-filament-widgets::widget>
