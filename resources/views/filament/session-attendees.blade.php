@if ($items->isEmpty())
    <p class="text-sm text-gray-500">Bu seans için henüz ödenmiş rezervasyon yok.</p>
@else
    <div class="divide-y divide-gray-200 dark:divide-white/10">
        @foreach ($items as $item)
            <div class="flex items-start justify-between gap-4 py-3 text-sm">
                <div>
                    <p class="font-medium">{{ implode(', ', $item->participants ?? [$item->order->customer_name]) }}</p>
                    <p class="text-gray-500">{{ $item->order->customer_name }} · {{ $item->order->customer_phone }} · {{ $item->order->customer_email }}</p>
                </div>
                <div class="text-right">
                    <p>{{ $item->quantity }} kişi</p>
                    <a href="{{ \App\Filament\Resources\Orders\OrderResource::getUrl('view', ['record' => $item->order]) }}" class="text-primary-600 underline">{{ $item->order->number }}</a>
                </div>
            </div>
        @endforeach
    </div>
    <p class="mt-3 text-sm text-gray-500">Toplam {{ $items->sum('quantity') }} kişi</p>
@endif
