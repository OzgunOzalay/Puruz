@extends('layouts.shop')
@use('App\Support\Money')

@section('title', 'Ödeme')
@section('noindex', '1')

@php
    $requiresShipping = $cart->requiresShipping();
    $workshopLines = $cart->lines()->filter->isWorkshop();
    $cityOptions = array_keys($districts);
@endphp

@section('content')
    <div class="container-shop pt-8 sm:pt-12" x-data="{ billingSame: {{ old('billing_same', $requiresShipping ? '1' : '0') ? 'true' : 'false' }}, billingType: '{{ old('billing_type', 'individual') }}', doc: null }">
        <a href="{{ route('cart.show') }}" class="inline-flex items-center gap-1 text-sm text-ink-soft hover:text-ink"><x-glyph name="arrow-left" class="size-4" /> sepete dön</a>
        <h1 class="mt-3 text-5xl leading-none">ödeme</h1>

        @if ($storeClosed)
            <p class="mt-6 rounded-2xl bg-tile/10 px-4 py-3 text-tile">{{ $settings['store_closed_message'] }}</p>
        @endif

        <form method="POST" action="{{ route('checkout.store') }}" class="mt-8 grid gap-10 lg:grid-cols-[1fr_380px]" novalidate>
            @csrf
            <div class="space-y-10">
                <section>
                    <h2 class="text-2xl">iletişim</h2>
                    <div class="mt-4 grid gap-4 sm:grid-cols-2">
                        <div class="sm:col-span-2">
                            <label for="customer_name" class="label">Ad soyad</label>
                            <input id="customer_name" name="customer_name" value="{{ old('customer_name') }}" autocomplete="name" required @class(['input', 'input-error' => $errors->has('customer_name')])>
                            @error('customer_name')<p class="field-error">{{ $message }}</p>@enderror
                        </div>
                        <div>
                            <label for="customer_email" class="label">E-posta</label>
                            <input id="customer_email" type="email" name="customer_email" value="{{ old('customer_email') }}" autocomplete="email" inputmode="email" required @class(['input', 'input-error' => $errors->has('customer_email')])>
                            @error('customer_email')<p class="field-error">{{ $message }}</p>@enderror
                            <p class="mt-1 text-xs text-ink-faint">Sipariş takip bağlantınızı bu adrese göndereceğiz.</p>
                        </div>
                        <div>
                            <label for="customer_phone" class="label">Cep telefonu</label>
                            <input id="customer_phone" type="tel" name="customer_phone" value="{{ old('customer_phone') }}" placeholder="05XX XXX XX XX" autocomplete="tel" inputmode="tel" required @class(['input', 'input-error' => $errors->has('customer_phone')])>
                            @error('customer_phone')<p class="field-error">{{ $message }}</p>@enderror
                        </div>
                    </div>
                </section>

                @if ($requiresShipping)
                    <section x-data="addressPicker(@js($districts), @js(old('shipping_city', '')), @js(old('shipping_district', '')))">
                        <h2 class="text-2xl">teslimat adresi</h2>
                        <div class="mt-4 grid gap-4 sm:grid-cols-2">
                            <div class="sm:col-span-2">
                                <label for="shipping_name" class="label">Alıcı adı <span class="font-normal text-ink-faint">(farklıysa)</span></label>
                                <input id="shipping_name" name="shipping_name" value="{{ old('shipping_name') }}" class="input">
                            </div>
                            <div>
                                <label for="shipping_city" class="label">İl</label>
                                <select id="shipping_city" name="shipping_city" x-model="city" @change="district = ''" @class(['input', 'input-error' => $errors->has('shipping_city')])>
                                    <option value="">Seçin</option>
                                    @foreach ($cityOptions as $city)
                                        <option value="{{ $city }}" @selected(old('shipping_city') === $city)>{{ $city }}</option>
                                    @endforeach
                                </select>
                                @error('shipping_city')<p class="field-error">{{ $message }}</p>@enderror
                            </div>
                            <div>
                                <label for="shipping_district" class="label">İlçe</label>
                                <select id="shipping_district" name="shipping_district" x-model="district" :disabled="!city" @class(['input', 'input-error' => $errors->has('shipping_district')])>
                                    <option value="">Seçin</option>
                                    <template x-for="d in cityDistricts" :key="d"><option :value="d" x-text="d" :selected="d === district"></option></template>
                                </select>
                                @error('shipping_district')<p class="field-error">{{ $message }}</p>@enderror
                            </div>
                            <div class="sm:col-span-2">
                                <label for="shipping_address" class="label">Açık adres</label>
                                <textarea id="shipping_address" name="shipping_address" rows="3" placeholder="Mahalle, sokak, bina ve daire no" autocomplete="street-address" @class(['input', 'input-error' => $errors->has('shipping_address')])>{{ old('shipping_address') }}</textarea>
                                @error('shipping_address')<p class="field-error">{{ $message }}</p>@enderror
                            </div>
                            <div>
                                <label for="shipping_postal_code" class="label">Posta kodu <span class="font-normal text-ink-faint">(isteğe bağlı)</span></label>
                                <input id="shipping_postal_code" name="shipping_postal_code" value="{{ old('shipping_postal_code') }}" inputmode="numeric" maxlength="5" autocomplete="postal-code" @class(['input', 'input-error' => $errors->has('shipping_postal_code')])>
                                @error('shipping_postal_code')<p class="field-error">{{ $message }}</p>@enderror
                            </div>
                        </div>
                    </section>
                @endif

                @if ($workshopLines->isNotEmpty())
                    <section>
                        <h2 class="text-2xl">atölye katılımcıları</h2>
                        <p class="mt-1 text-sm text-ink-soft">Boş bırakırsanız rezervasyon sizin adınıza yapılır.</p>
                        <div class="mt-4 space-y-5">
                            @foreach ($workshopLines as $line)
                                <div class="card p-4">
                                    <p class="font-medium">{{ $line->product->name }}</p>
                                    <p class="text-sm text-ink-soft">{{ $line->session->displayLabel() }}</p>
                                    <div class="mt-3 grid gap-3 sm:grid-cols-2">
                                        @for ($i = 0; $i < $line->quantity; $i++)
                                            <input name="participants[{{ $line->key }}][]" value="{{ old('participants.'.$line->key.'.'.$i) }}" placeholder="{{ $i + 1 }}. katılımcı adı" class="input">
                                        @endfor
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </section>
                @endif

                <section>
                    <h2 class="text-2xl">fatura bilgileri</h2>
                    <div class="mt-4 flex gap-2">
                        <label class="cursor-pointer"><input type="radio" name="billing_type" value="individual" x-model="billingType" class="peer sr-only"><span class="chip peer-checked:border-ink peer-checked:bg-ink peer-checked:text-paper">bireysel</span></label>
                        <label class="cursor-pointer"><input type="radio" name="billing_type" value="corporate" x-model="billingType" class="peer sr-only"><span class="chip peer-checked:border-ink peer-checked:bg-ink peer-checked:text-paper">kurumsal</span></label>
                    </div>

                    <div class="mt-4 grid gap-4 sm:grid-cols-2">
                        <div x-show="billingType === 'individual'">
                            <label for="identity_number" class="label">T.C. kimlik no <span class="font-normal text-ink-faint">(isteğe bağlı)</span></label>
                            <input id="identity_number" name="identity_number" value="{{ old('identity_number') }}" inputmode="numeric" maxlength="11" @class(['input', 'input-error' => $errors->has('identity_number')])>
                            @error('identity_number')<p class="field-error">{{ $message }}</p>@enderror
                        </div>
                        <template x-if="billingType === 'corporate'">
                            <div class="grid gap-4 sm:col-span-2 sm:grid-cols-2">
                                <div class="sm:col-span-2">
                                    <label for="billing_company" class="label">Firma unvanı</label>
                                    <input id="billing_company" name="billing_company" value="{{ old('billing_company') }}" class="input">
                                    @error('billing_company')<p class="field-error">{{ $message }}</p>@enderror
                                </div>
                                <div>
                                    <label for="billing_tax_office" class="label">Vergi dairesi</label>
                                    <input id="billing_tax_office" name="billing_tax_office" value="{{ old('billing_tax_office') }}" class="input">
                                    @error('billing_tax_office')<p class="field-error">{{ $message }}</p>@enderror
                                </div>
                                <div>
                                    <label for="billing_tax_number" class="label">Vergi no</label>
                                    <input id="billing_tax_number" name="billing_tax_number" value="{{ old('billing_tax_number') }}" inputmode="numeric" maxlength="11" class="input">
                                    @error('billing_tax_number')<p class="field-error">{{ $message }}</p>@enderror
                                </div>
                            </div>
                        </template>
                    </div>

                    @if ($requiresShipping)
                        <label class="mt-4 flex items-center gap-2 text-[15px]">
                            <input type="hidden" name="billing_same" value="0">
                            <input type="checkbox" name="billing_same" value="1" x-model="billingSame" class="size-4 rounded border-line text-ink focus:ring-ink">
                            Fatura adresim teslimat adresiyle aynı
                        </label>
                    @else
                        <input type="hidden" name="billing_same" value="0">
                    @endif

                    <div x-show="!billingSame" x-cloak x-data="addressPicker(@js($districts), @js(old('billing_city', '')), @js(old('billing_district', '')))" class="mt-4 grid gap-4 sm:grid-cols-2">
                        <div>
                            <label for="billing_city" class="label">İl</label>
                            <select id="billing_city" name="billing_city" x-model="city" @change="district = ''" :disabled="billingSame" @class(['input', 'input-error' => $errors->has('billing_city')])>
                                <option value="">Seçin</option>
                                @foreach ($cityOptions as $city)
                                    <option value="{{ $city }}" @selected(old('billing_city') === $city)>{{ $city }}</option>
                                @endforeach
                            </select>
                            @error('billing_city')<p class="field-error">{{ $message }}</p>@enderror
                        </div>
                        <div>
                            <label for="billing_district" class="label">İlçe</label>
                            <select id="billing_district" name="billing_district" x-model="district" :disabled="billingSame || !city" @class(['input', 'input-error' => $errors->has('billing_district')])>
                                <option value="">Seçin</option>
                                <template x-for="d in cityDistricts" :key="d"><option :value="d" x-text="d" :selected="d === district"></option></template>
                            </select>
                            @error('billing_district')<p class="field-error">{{ $message }}</p>@enderror
                        </div>
                        <div class="sm:col-span-2">
                            <label for="billing_address" class="label">Fatura adresi</label>
                            <textarea id="billing_address" name="billing_address" rows="2" :disabled="billingSame" @class(['input', 'input-error' => $errors->has('billing_address')])>{{ old('billing_address') }}</textarea>
                            @error('billing_address')<p class="field-error">{{ $message }}</p>@enderror
                        </div>
                    </div>
                </section>

                <section>
                    <label for="customer_note" class="label">Sipariş notu <span class="font-normal text-ink-faint">(isteğe bağlı)</span></label>
                    <textarea id="customer_note" name="customer_note" rows="2" placeholder="Hediye paketi, teslimat notu…" class="input">{{ old('customer_note') }}</textarea>
                </section>
            </div>

            <aside class="space-y-5 lg:sticky lg:top-24 lg:self-start">
                <div class="card p-5">
                    <h2 class="text-2xl">siparişiniz</h2>
                    <ul class="my-4 space-y-3">
                        @foreach ($cart->lines() as $line)
                            <li class="flex gap-3 text-sm">
                                <div class="relative shrink-0">
                                    <img src="{{ $line->product->coverUrl(400) }}" alt="" class="size-14 rounded-xl bg-paper-deep object-cover">
                                    <span class="absolute -right-1.5 -top-1.5 flex size-5 items-center justify-center rounded-full bg-ink text-[11px] text-paper">{{ $line->quantity }}</span>
                                </div>
                                <div class="min-w-0 flex-1">
                                    <p class="font-medium">{{ $line->product->name }}</p>
                                    @if ($line->description())<p class="text-ink-soft">{{ $line->description() }}</p>@endif
                                </div>
                                <p class="shrink-0">{{ Money::format($line->total()) }}</p>
                            </li>
                        @endforeach
                    </ul>
                    @include('shop.partials.summary')

                    <div class="mt-5 space-y-3 text-sm">
                        <label class="flex items-start gap-2.5">
                            <input type="checkbox" name="accept_terms" value="1" @checked(old('accept_terms')) class="mt-0.5 size-4 shrink-0 rounded border-line text-ink focus:ring-ink">
                            <span><button type="button" class="underline" @click="doc = 'on-bilgilendirme-formu'">Ön Bilgilendirme Formu</button>’nu ve <button type="button" class="underline" @click="doc = 'mesafeli-satis-sozlesmesi'">Mesafeli Satış Sözleşmesi</button>’ni okudum, onaylıyorum.</span>
                        </label>
                        @error('accept_terms')<p class="field-error">{{ $message }}</p>@enderror
                        <label class="flex items-start gap-2.5">
                            <input type="checkbox" name="accept_kvkk" value="1" @checked(old('accept_kvkk')) class="mt-0.5 size-4 shrink-0 rounded border-line text-ink focus:ring-ink">
                            <span><button type="button" class="underline" @click="doc = 'kvkk-aydinlatma-metni'">KVKK Aydınlatma Metni</button>’ni okudum.</span>
                        </label>
                        @error('accept_kvkk')<p class="field-error">{{ $message }}</p>@enderror
                        <label class="flex items-start gap-2.5 text-ink-soft">
                            <input type="checkbox" name="marketing_consent" value="1" @checked(old('marketing_consent')) class="mt-0.5 size-4 shrink-0 rounded border-line text-ink focus:ring-ink">
                            <span>Yeni ürün ve atölye duyurularını e-posta ile almak istiyorum. <span class="text-ink-faint">(isteğe bağlı)</span></span>
                        </label>
                    </div>

                    <button type="submit" class="btn-primary mt-5 w-full py-4" @disabled($storeClosed)>
                        <x-glyph name="lock" class="size-4" /> {{ Money::format($cart->total()) }} öde
                    </button>
                    <p class="mt-3 flex items-center justify-center gap-1.5 text-center text-xs text-ink-faint">
                        <x-glyph name="card" class="size-4" /> Kart bilgileriniz iyzico’nun güvenli ödeme sayfasında alınır.
                    </p>
                </div>
            </aside>
        </form>

        {{-- Legal texts open in place, so the customer does not lose the form. --}}
        <div x-show="doc" x-cloak class="fixed inset-0 z-50 flex items-end justify-center bg-ink/30 sm:items-center" @keydown.escape.window="doc = null" @click.self="doc = null">
            <div class="max-h-[85dvh] w-full max-w-2xl overflow-y-auto rounded-t-3xl bg-paper p-6 sm:rounded-3xl">
                <div class="mb-3 flex justify-end"><button type="button" @click="doc = null" class="rounded-full p-2" aria-label="Kapat"><x-glyph name="close" /></button></div>
                @foreach ($legalPages as $slug => $legalPage)
                    <article x-show="doc === '{{ $slug }}'" class="prose prose-neutral prose-sm max-w-none">
                        <h2 class="font-hand font-medium">{{ $legalPage->title }}</h2>
                        @include('contracts.cart-summary', ['cart' => $cart])
                        {!! $legalPage->content !!}
                    </article>
                @endforeach
            </div>
        </div>
    </div>
@endsection
