@props(['address' => null])
{{-- The workshop building, from the opening poster's ink sketch; it is wiped in as if being drawn. --}}
<figure {{ $attributes }}>
    <img src="{{ asset('images/site/bina.webp') }}" alt="Pürüz Atölye'nin bulunduğu bina, mürekkep çizim" width="900" height="653" loading="lazy" decoding="async"
         class="mask-draw mx-auto w-full max-w-xl">
    @if ($address)
        <figcaption class="mt-4 text-center font-hand text-lg lowercase sm:text-xl">{{ $address }}</figcaption>
    @endif
</figure>
