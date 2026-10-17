@use('App\Support\Money')
@php
    $pages = \App\Models\Page::query()->whereIn('slug', ['on-bilgilendirme-formu', 'mesafeli-satis-sozlesmesi'])->get()->keyBy('slug');
@endphp
{{-- Frozen copy of what the customer accepted, stored on the order and emailed as PDF. --}}
<div class="contract">
    <p><strong>Sipariş no:</strong> {{ $order->number }} · <strong>Tarih:</strong> {{ $order->created_at->format('d.m.Y H:i') }}</p>

    <h3>Satıcı</h3>
    @include('contracts.seller')

    <h3>Alıcı</h3>
    <table>
        <tr><th align="left">Ad soyad / unvan</th><td>{{ $order->billing_type === 'corporate' ? $order->billing_company : $order->customer_name }}</td></tr>
        <tr><th align="left">E-posta</th><td>{{ $order->customer_email }}</td></tr>
        <tr><th align="left">Telefon</th><td>{{ $order->customer_phone }}</td></tr>
        @if ($order->requires_shipping)
            <tr><th align="left">Teslimat adresi</th><td>{{ $order->shipping_name }}, {{ $order->shipping_address }} {{ $order->shipping_district }}/{{ $order->shipping_city }}</td></tr>
        @endif
        <tr><th align="left">Fatura adresi</th><td>{{ $order->billing_address }} {{ $order->billing_district }}/{{ $order->billing_city }}</td></tr>
        @if ($order->billing_type === 'corporate')
            <tr><th align="left">Vergi dairesi / no</th><td>{{ $order->billing_tax_office }} / {{ $order->billing_tax_number }}</td></tr>
        @endif
    </table>

    <h3>Ürün ve hizmetler</h3>
    <table>
        <thead><tr><th align="left">Açıklama</th><th align="right">Adet</th><th align="right">Birim fiyat</th><th align="right">Tutar</th></tr></thead>
        <tbody>
            @foreach ($order->items as $item)
                <tr><td>{{ $item->displayName() }}</td><td align="right">{{ $item->quantity }}</td><td align="right">{{ Money::format($item->unit_price) }}</td><td align="right">{{ Money::format($item->line_total) }}</td></tr>
            @endforeach
            @if ($order->discount_total)<tr><td colspan="3">İndirim ({{ $order->coupon_code }})</td><td align="right">−{{ Money::format($order->discount_total) }}</td></tr>@endif
            @if ($order->requires_shipping)<tr><td colspan="3">Kargo</td><td align="right">{{ Money::format($order->shipping_total) }}</td></tr>@endif
            <tr><th colspan="3" align="left">Toplam (KDV dahil)</th><th align="right">{{ Money::format($order->total) }}</th></tr>
        </tbody>
    </table>
    <p>Ödeme şekli: kredi/banka kartı ile tek seferde veya taksitle (iyzico ödeme altyapısı).</p>

    @foreach ($pages as $page)
        <h2>{{ $page->title }}</h2>
        {!! $page->content !!}
    @endforeach

    <p><em>Alıcı bu metinleri {{ $order->terms_accepted_at?->format('d.m.Y H:i') }} tarihinde {{ $order->ip_address }} IP adresinden elektronik ortamda onaylamıştır.</em></p>
</div>
