@use('App\Support\Money')
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border-top:1px solid #e6e2d8;margin-top:8px;">
    @foreach ($order->items as $item)
        <tr>
            <td style="padding:10px 0;border-bottom:1px solid #e6e2d8;">
                <strong>{{ $item->name }}</strong> × {{ $item->quantity }}
                @if ($item->variant_name)<br><span style="color:#57554f;font-size:13px;">{{ $item->variant_name }}</span>@endif
                @if ($item->session_label)<br><span style="color:#57554f;font-size:13px;">{{ $item->session_label }}</span>@endif
            </td>
            <td style="padding:10px 0;border-bottom:1px solid #e6e2d8;" align="right">{{ Money::format($item->line_total) }}</td>
        </tr>
    @endforeach
    @if ($order->discount_total)
        <tr><td style="padding:6px 0;color:#3f6b4f;">İndirim</td><td align="right" style="color:#3f6b4f;">−{{ Money::format($order->discount_total) }}</td></tr>
    @endif
    @if ($order->requires_shipping)
        <tr><td style="padding:6px 0;color:#57554f;">Kargo</td><td align="right">{{ $order->shipping_total ? Money::format($order->shipping_total) : 'ücretsiz' }}</td></tr>
    @endif
    <tr><td style="padding:8px 0;font-size:17px;"><strong>Toplam</strong></td><td align="right" style="font-size:17px;"><strong>{{ Money::format($order->total) }}</strong></td></tr>
</table>
