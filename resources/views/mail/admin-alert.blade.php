@extends('mail.layout')

@section('body')
    <p style="font-size:22px;margin:0 0 12px;">{{ $order->number }} kontrol edilmeli</p>
    <p style="margin:0;">{{ $alert }}</p>
    @include('mail.partials-button', ['url' => url('/yonetim/siparisler/'.$order->id), 'label' => 'Siparişi aç'])
@endsection
