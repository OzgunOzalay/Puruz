@php($order = $getRecord())
<ol class="space-y-3 text-sm">
    @foreach ($order->histories->sortByDesc('id') as $history)
        <li class="flex gap-3">
            <span class="mt-1.5 size-2 shrink-0 rounded-full bg-primary-500"></span>
            <div>
                <p>
                    <span class="font-medium">{{ $history->to_status->getLabel() }}</span>
                    <span class="text-gray-500">· {{ \App\Support\Human::date($history->created_at) }}{{ $history->user ? ' · '.$history->user->name : '' }}</span>
                    @if ($history->customer_notified)<span class="ml-1 text-xs text-success-600">e-posta gönderildi</span>@endif
                </p>
                @if ($history->note)<p class="text-gray-600 dark:text-gray-400">{{ $history->note }}</p>@endif
            </div>
        </li>
    @endforeach
</ol>
