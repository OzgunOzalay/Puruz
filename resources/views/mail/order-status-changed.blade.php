@extends('mail.layout')

@section('body')
    <p style="font-size:22px;margin:0 0 12px;">{{ $status->getLabel() }}</p>
    <p style="margin:0 0 8px;">Merhaba {{ $order->firstName() }}, {{ $order->number }} numaralı siparişinizle ilgili bir güncelleme var:</p>
    <p style="margin:0 0 8px;">{{ $status->customerMessage() }}</p>
    @if ($note)
        <p style="margin:16px 0;padding:12px 16px;background:#f5f4ef;border-radius:12px;">{{ $note }}</p>
    @endif
    @if ($status === \App\Enums\OrderStatus::Shipped && $order->cargo_tracking_number)
        <p style="margin:16px 0 0;">{{ $order->cargo_company?->getLabel() }} · takip no: <strong>{{ $order->cargo_tracking_number }}</strong></p>
        @if ($order->cargoTrackingUrl())
            @include('mail.partials-button', ['url' => $order->cargoTrackingUrl(), 'label' => 'Kargomu takip et'])
        @endif
    @endif
    @include('mail.partials-button', ['url' => $order->trackingUrl(), 'label' => 'Sipariş detayları'])
@endsection
