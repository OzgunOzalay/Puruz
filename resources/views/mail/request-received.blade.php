@extends('mail.layout')

@section('body')
    <p style="font-size:22px;margin:0 0 12px;">Talebinizi aldık</p>
    <p style="margin:0 0 12px;">“{{ $similar->product->name }}” benzeri bir parça için yazdığınız talep bize ulaştı. En kısa sürede size döneceğiz.</p>
    <p style="margin:12px 0;padding:12px 16px;background:#f5f4ef;border-radius:12px;white-space:pre-line;">{{ $similar->message }}</p>
@endsection
