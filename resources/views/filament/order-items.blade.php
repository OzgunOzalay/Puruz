@use('App\Support\Money')
@php($order = $getRecord())
<div class="overflow-x-auto">
    <table class="w-full text-sm">
        <thead>
            <tr class="border-b border-gray-200 text-left text-gray-500 dark:border-white/10">
                <th class="py-2 pr-3 font-medium">Ürün</th>
                <th class="py-2 pr-3 text-right font-medium">Adet</th>
                <th class="py-2 pr-3 text-right font-medium">Birim</th>
                <th class="py-2 text-right font-medium">Tutar</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($order->items as $item)
                <tr class="border-b border-gray-100 align-top dark:border-white/5">
                    <td class="py-2.5 pr-3">
                        <p class="font-medium">{{ $item->name }}</p>
                        @if ($item->variant_name)<p class="text-gray-500">{{ $item->variant_name }}</p>@endif
                        @if ($item->session_label)<p class="text-gray-500">{{ $item->session_label }}</p>@endif
                        @if ($item->participants)<p class="text-gray-500">Katılımcılar: {{ implode(', ', $item->participants) }}</p>@endif
                        @if ($item->sku)<p class="text-xs text-gray-400">{{ $item->sku }}</p>@endif
                        @if ($item->refunded_total)<p class="text-xs text-danger-600">İade: {{ Money::format($item->refunded_total) }}</p>@endif
                    </td>
                    <td class="py-2.5 pr-3 text-right">{{ $item->quantity }}</td>
                    <td class="py-2.5 pr-3 text-right">{{ Money::format($item->unit_price) }}</td>
                    <td class="py-2.5 text-right">{{ Money::format($item->line_total) }}</td>
                </tr>
            @endforeach
        </tbody>
        <tfoot class="text-right">
            <tr><td colspan="3" class="pt-3 pr-3 text-gray-500">Ara toplam</td><td class="pt-3">{{ Money::format($order->subtotal) }}</td></tr>
            @if ($order->requires_shipping)
                <tr><td colspan="3" class="pr-3 text-gray-500">Kargo</td><td>{{ Money::format($order->shipping_total) }}</td></tr>
            @endif
            <tr class="text-base font-semibold"><td colspan="3" class="pr-3 pt-1">Toplam</td><td class="pt-1">{{ Money::format($order->total) }}</td></tr>
        </tfoot>
    </table>
</div>
