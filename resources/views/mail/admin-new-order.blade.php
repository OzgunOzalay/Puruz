@extends('mail.layout')

@section('body')
    <p style="font-size:22px;margin:0 0 12px;">Yeni sipariş: {{ $order->number }}</p>
    <p style="margin:0;">{{ $order->customer_name }} · {{ $order->customer_email }} · {{ $order->customer_phone }}</p>
    @if ($order->utm_source)<p style="margin:4px 0 0;color:#57554f;font-size:13px;">Kaynak: {{ $order->utm_source }}{{ $order->utm_medium ? ' / '.$order->utm_medium : '' }}</p>@endif
    @if ($order->customer_note)<p style="margin:12px 0;padding:12px 16px;background:#f5f4ef;border-radius:12px;">Not: {{ $order->customer_note }}</p>@endif
    @include('mail.partials-items')
    @include('mail.partials-button', ['url' => url('/yonetim/orders/'.$order->id), 'label' => 'Yönetim panelinde aç'])
@endsection
