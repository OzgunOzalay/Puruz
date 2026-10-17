@extends('mail.layout')

@section('body')
    <p style="font-size:22px;margin:0 0 12px;">Sipariş takip bağlantınız</p>
    <p style="margin:0;">Merhaba {{ $order->firstName() }}, {{ $order->number }} numaralı siparişinizin takip bağlantısını istediniz. Bu isteği siz yapmadıysanız bu e-postayı yok sayabilirsiniz.</p>
    @include('mail.partials-button', ['url' => $order->trackingUrl(), 'label' => 'Siparişimi görüntüle'])
@endsection
