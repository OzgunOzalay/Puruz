@php($s = \App\Support\Settings::all())
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="color-scheme" content="light">
    <title>{{ $s['shop_name'] }}</title>
</head>
<body style="margin:0;padding:0;background:#f5f4ef;font-family:-apple-system,'Segoe UI',Roboto,Helvetica,Arial,sans-serif;color:#141414;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f5f4ef;">
        <tr><td align="center" style="padding:32px 16px;">
            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:560px;">
                <tr><td style="padding:0 0 24px;" align="center">
                    <img src="{{ asset('images/brand/logo-email.png') }}" width="150" alt="{{ $s['shop_name'] }}" style="display:block;border:0;">
                </td></tr>
                <tr><td style="background:#ffffff;border-radius:20px;padding:32px 28px;font-size:15px;line-height:1.6;">
                    @yield('body')
                </td></tr>
                <tr><td style="padding:24px 8px;font-size:12px;line-height:1.6;color:#8a877e;" align="center">
                    {{ $s['legal_name'] ?: $s['shop_name'] }} · {{ $s['contact_phone'] }} · {{ $s['contact_email'] }}<br>
                    @if ($s['instagram_handle'])<a href="https://instagram.com/{{ $s['instagram_handle'] }}" style="color:#57554f;">instagram.com/{{ $s['instagram_handle'] }}</a>@endif
                </td></tr>
            </table>
        </td></tr>
    </table>
</body>
</html>
