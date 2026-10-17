@extends('mail.layout')

@section('body')
    <p style="font-size:22px;margin:0 0 12px;">Yeni talep: {{ $similar->product->name }} gibi</p>
    <p style="margin:0;">{{ $similar->displayContact() }}</p>
    <p style="margin:12px 0;padding:12px 16px;background:#f5f4ef;border-radius:12px;white-space:pre-line;">{{ $similar->message }}</p>
    @include('mail.partials-button', ['url' => url('/yonetim/talepler/'.$similar->id), 'label' => 'Talebi aç'])
@endsection
