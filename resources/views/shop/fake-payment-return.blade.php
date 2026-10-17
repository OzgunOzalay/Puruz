<!DOCTYPE html>
<html lang="tr">
<head><meta charset="utf-8"><title>Yönlendiriliyor…</title></head>
<body onload="document.forms[0].submit()">
    <form method="POST" action="{{ route('payment.callback') }}">
        <input type="hidden" name="token" value="{{ $token }}">
        <noscript><button>Devam</button></noscript>
    </form>
</body>
</html>
