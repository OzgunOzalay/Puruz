@extends('mail.layout')

@section('body')
    <p style="font-size:22px;margin:0 0 12px;">İstediğiniz parça hazır</p>
    <p style="margin:0 0 12px;">“{{ $similar->product->name }}” benzeri parça için talebinizi hazırladık. Aşağıdaki linkten görüp satın alabilirsiniz.</p>
    @include('mail.partials-button', ['url' => $piece->url(), 'label' => 'Parçayı gör'])
@endsection
