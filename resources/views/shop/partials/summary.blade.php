@use('App\Support\Money')
<dl class="space-y-2 text-[15px]">
    <div class="flex justify-between"><dt class="text-ink-soft">Ara toplam</dt><dd>{{ Money::format($cart->subtotal()) }}</dd></div>
    @if ($cart->discount() > 0)
        <div class="flex justify-between text-moss"><dt>İndirim ({{ $cart->coupon()->code }})</dt><dd>−{{ Money::format($cart->discount()) }}</dd></div>
    @endif
    @if ($cart->requiresShipping())
        <div class="flex justify-between"><dt class="text-ink-soft">Kargo</dt><dd>{{ $cart->shipping() === 0 ? 'ücretsiz' : Money::format($cart->shipping()) }}</dd></div>
    @endif
    <div class="flex justify-between border-t border-line pt-3 text-lg font-medium"><dt>Toplam</dt><dd>{{ Money::format($cart->total()) }}</dd></div>
    <p class="text-xs text-ink-faint">Fiyatlara KDV dahildir.</p>
</dl>
