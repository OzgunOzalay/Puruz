@php($step = \App\Filament\Resources\Orders\OrderStatusText::nextStep($getRecord()))
@if ($step)
    <div class="flex items-center gap-3 rounded-lg bg-primary-50 px-4 py-3 text-sm font-medium text-primary-800 dark:bg-primary-500/10 dark:text-primary-300">
        <x-filament::icon icon="heroicon-o-arrow-right-circle" class="size-5" />
        <span>{{ $step[0] }}</span>
        @if ($step[1])<span class="text-primary-600/70 dark:text-primary-300/70">— yukarıdaki “{{ $step[1] === 'ship' ? 'Kargoya ver' : 'Teslim edildi' }}” düğmesini kullanın</span>@endif
    </div>
@endif
