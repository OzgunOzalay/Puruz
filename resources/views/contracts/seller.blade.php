@php($s = \App\Support\Settings::all())
<table>
    <tr><th align="left">Satıcı</th><td>{{ $s['legal_name'] ?: $s['shop_name'] }}</td></tr>
    @if ($s['address'])<tr><th align="left">Adres</th><td>{{ $s['address'] }}</td></tr>@endif
    <tr><th align="left">Telefon</th><td>{{ $s['contact_phone'] }}</td></tr>
    <tr><th align="left">E-posta</th><td>{{ $s['contact_email'] }}</td></tr>
    @if ($s['tax_office'] || $s['tax_number'])<tr><th align="left">Vergi dairesi / no</th><td>{{ $s['tax_office'] }} / {{ $s['tax_number'] }}</td></tr>@endif
    @if ($s['mersis_number'])<tr><th align="left">MERSİS no</th><td>{{ $s['mersis_number'] }}</td></tr>@endif
</table>
