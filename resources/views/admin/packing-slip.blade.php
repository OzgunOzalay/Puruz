@php($s = \App\Support\Settings::all())
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="utf-8">
    <title>Paketleme fişi {{ $order->number }}</title>
    <style>
        body { font-family: -apple-system, 'Segoe UI', Roboto, sans-serif; color: #141414; margin: 32px; font-size: 14px; }
        header { display: flex; justify-content: space-between; align-items: flex-start; border-bottom: 2px solid #141414; padding-bottom: 16px; }
        h1 { font-size: 20px; margin: 0; }
        .grid { display: grid; grid-template-columns: 1fr 1fr; gap: 24px; margin: 24px 0; }
        .box { border: 1px solid #ccc; border-radius: 8px; padding: 12px 16px; white-space: pre-line; }
        table { width: 100%; border-collapse: collapse; }
        th, td { text-align: left; padding: 8px 4px; border-bottom: 1px solid #ddd; }
        .check { width: 18px; height: 18px; border: 1.5px solid #141414; border-radius: 4px; display: inline-block; }
        .note { margin-top: 24px; padding: 12px 16px; background: #f5f4ef; border-radius: 8px; }
        @media print { body { margin: 12mm; } button { display: none; } }
    </style>
</head>
<body>
    <button onclick="window.print()" style="float:right;padding:8px 16px;">Yazdır</button>
    <header>
        <div>
            <img src="{{ asset('images/brand/logo.svg') }}" alt="" style="height:40px">
        </div>
        <div style="text-align:right">
            <h1>{{ $order->number }}</h1>
            <div>{{ $order->created_at->format('d.m.Y H:i') }}</div>
        </div>
    </header>
    <div class="grid">
        <div>
            <strong>Alıcı</strong>
            <div class="box">{{ $order->shipping_name ?: $order->customer_name }}
{{ $order->shipping_address }}
{{ $order->shipping_district }} / {{ $order->shipping_city }} {{ $order->shipping_postal_code }}
Tel: {{ $order->customer_phone }}</div>
        </div>
        <div>
            <strong>Gönderen</strong>
            <div class="box">{{ $s['legal_name'] ?: $s['shop_name'] }}
{{ $s['address'] }}
Tel: {{ $s['contact_phone'] }}</div>
        </div>
    </div>
    <table>
        <thead><tr><th style="width:30px"></th><th>Ürün</th><th>SKU</th><th style="text-align:right">Adet</th></tr></thead>
        <tbody>
            @foreach ($order->items as $item)
                <tr><td><span class="check"></span></td><td>{{ $item->displayName() }}</td><td>{{ $item->sku }}</td><td style="text-align:right">{{ $item->quantity }}</td></tr>
            @endforeach
        </tbody>
    </table>
    @if ($order->customer_note)
        <div class="note"><strong>Müşteri notu:</strong> {{ $order->customer_note }}</div>
    @endif
    <p style="margin-top:40px;font-size:13px;color:#57554f">Bizi tercih ettiğiniz için teşekkürler! Sorularınız için {{ $s['contact_phone'] }} · {{ '@'.$s['instagram_handle'] }}</p>
</body>
</html>
