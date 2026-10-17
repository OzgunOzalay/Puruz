@props(['title', 'link' => null, 'linkText' => 'tümünü gör'])
<div {{ $attributes->merge(['class' => 'mb-6 flex items-end justify-between gap-4']) }}>
    <h2 class="text-3xl leading-none sm:text-4xl">{{ $title }}</h2>
    @if ($link)
        <a href="{{ $link }}" class="inline-flex shrink-0 items-center gap-1 text-sm text-ink-soft hover:text-ink">
            {{ $linkText }} <x-glyph name="arrow-right" class="size-4" />
        </a>
    @endif
</div>
