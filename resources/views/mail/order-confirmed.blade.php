@extends('mail.layout')

@section('body')
    <p style="font-size:22px;margin:0 0 12px;">Teşekkürler {{ $order->firstName() }}!</p>
    <p style="margin:0 0 8px;">
        @if ($order->requires_shipping)
            <strong>{{ $order->number }}</strong> numaralı siparişinizin ödemesi alındı. Ürünlerinizi özenle hazırlayıp kargoya verdiğimizde size tekrar haber vereceğiz.
        @else
            <strong>{{ $order->number }}</strong> numaralı atölye rezervasyonunuz onaylandı. Takvim dosyası bu e-postanın ekinde.
        @endif
    </p>
    <p style="margin:0;color:#57554f;">Siparişinizin durumunu aşağıdaki bağlantıdan her zaman takip edebilirsiniz.</p>
    @include('mail.partials-button', ['url' => $order->trackingUrl(), 'label' => 'Siparişimi takip et'])
    @include('mail.partials-items')
    @if ($order->requires_shipping)
        <p style="margin:20px 0 0;color:#57554f;font-size:13px;">Teslimat adresi: {{ $order->shipping_name }}, {{ $order->shipping_address }} {{ $order->shipping_district }}/{{ $order->shipping_city }}</p>
    @endif
    <p style="margin:16px 0 0;color:#8a877e;font-size:12px;">Ön Bilgilendirme Formu ve Mesafeli Satış Sözleşmesi’nin bir kopyası ekte yer alıyor.</p>
@endsection
