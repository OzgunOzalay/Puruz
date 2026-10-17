@php($hasErrors = $errors->hasAny(['message', 'contact', 'accept_kvkk']))
<div x-data="{ open: @js($hasErrors) }" x-on:open-similar-request.window="open = true" x-show="open" x-cloak
     class="fixed inset-0 z-40 flex items-end justify-center bg-ink/40 sm:items-center" @keydown.escape.window="open = false">
    <form method="POST" action="{{ route('shop.similar-request', $product->slug) }}" id="benzerini-iste"
          class="w-full max-w-lg rounded-t-3xl bg-paper p-6 shadow-xl sm:rounded-3xl" @click.outside="open = false">
        @csrf
        <div class="mb-4 flex items-start justify-between gap-4">
            <h2 class="font-hand text-2xl">benzerini iste</h2>
            <button type="button" @click="open = false" aria-label="Kapat"><x-glyph name="close" class="size-5" /></button>
        </div>

        {{-- Honeypot: hidden from people, filled by bots. --}}
        <input type="text" name="website" tabindex="-1" autocomplete="off" class="hidden" aria-hidden="true">

        <label for="similar-message" class="label">Nasıl bir parça istersiniz?</label>
        <textarea id="similar-message" name="message" rows="5" maxlength="2000" required class="input"
                  placeholder="Renk, ölçü, adet, ne zamana lazım olduğu…">{{ old('message') }}</textarea>
        @error('message')<p class="field-error">{{ $message }}</p>@enderror

        <label for="similar-contact" class="label mt-4">Telefon veya e-posta</label>
        <input id="similar-contact" name="contact" value="{{ old('contact') }}" required class="input" autocomplete="email">
        @error('contact')<p class="field-error">{{ $message }}</p>@enderror

        <label class="mt-4 flex items-start gap-2 text-sm text-ink-soft">
            <input type="checkbox" name="accept_kvkk" value="1" @checked(old('accept_kvkk')) class="mt-0.5 size-4 shrink-0 rounded border-line text-ink focus:ring-ink">
            <span><a href="{{ url('/sayfa/kvkk-aydinlatma-metni') }}" target="_blank" class="underline">KVKK Aydınlatma Metni</a>’ni okudum.</span>
        </label>
        @error('accept_kvkk')<p class="field-error">{{ $message }}</p>@enderror

        <button class="btn-primary mt-6 w-full">gönder</button>
    </form>
</div>
