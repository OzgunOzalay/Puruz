<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="utf-8">
    <title>Mesafeli Satış Sözleşmesi {{ $order->number }}</title>
    <style>
        /* DejaVu Sans ships with dompdf and covers Turkish characters. */
        body { font-family: 'DejaVu Sans', sans-serif; font-size: 10px; color: #141414; line-height: 1.45; }
        h1 { font-size: 16px; margin: 0 0 8px; }
        h2 { font-size: 13px; margin: 18px 0 6px; }
        h3 { font-size: 11px; margin: 14px 0 4px; }
        table { width: 100%; border-collapse: collapse; margin: 4px 0 8px; }
        th, td { border-bottom: 1px solid #ddd; padding: 4px 6px; vertical-align: top; }
    </style>
</head>
<body>
    <h1>{{ \App\Support\Settings::get('shop_name') }} · Sipariş {{ $order->number }}</h1>
    {!! $order->contract_snapshot !!}
</body>
</html>
