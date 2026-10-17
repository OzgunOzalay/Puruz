<x-filament-panels::page>
    <div
        x-load
        x-load-src="{{ \Illuminate\Support\Facades\Vite::asset('resources/js/filament/workshop-calendar.js') }}"
        x-data="workshopCalendar({ shopNow: @js(now()->format('Y-m-d\TH:i:s')) })"
        x-on:workshop-calendar-refresh.window="refresh()"
        wire:ignore
        class="puruz-calendar rounded-xl bg-white p-3 shadow-sm ring-1 ring-gray-950/5 sm:p-4 dark:bg-gray-900 dark:ring-white/10"
    >
        <div x-ref="calendar"></div>
    </div>

    <p class="text-sm text-gray-500 dark:text-gray-400">
        Boş bir güne tıklayarak (hafta görünümünde saatleri sürükleyerek) seans ekleyin. Seansı taşımak için sürükleyin,
        süresini değiştirmek için alt kenarından çekin. Rezervasyonu olan seanslar sürüklenemez.
    </p>
</x-filament-panels::page>
