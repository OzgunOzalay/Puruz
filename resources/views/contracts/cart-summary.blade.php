@use('App\Support\Money')
<h3>Satıcı bilgileri</h3>
@include('contracts.seller')
<h3>Sözleşme konusu ürün/hizmetler</h3>
<table>
    <thead><tr><th align="left">Ürün / hizmet</th><th align="right">Adet</th><th align="right">Tutar (KDV dahil)</th></tr></thead>
    <tbody>
        @foreach ($cart->lines() as $line)
            <tr><td>{{ $line->product->name }}{{ $line->description() ? ' – '.$line->description() : '' }}</td><td align="right">{{ $line->quantity }}</td><td align="right">{{ Money::format($line->total()) }}</td></tr>
        @endforeach
        @if ($cart->requiresShipping())<tr><td>Kargo</td><td></td><td align="right">{{ Money::format($cart->shipping()) }}</td></tr>@endif
        <tr><th align="left">Toplam</th><td></td><th align="right">{{ Money::format($cart->total()) }}</th></tr>
    </tbody>
</table>
<p>Ödeme yöntemi: kredi/banka kartı (iyzico). Alıcı bilgileri, sipariş formunda girdiğiniz bilgilerdir.</p>
