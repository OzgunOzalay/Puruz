@props(['title', 'link' => null, 'linkText' => 'tümünü gör'])
<div {{ $attributes->merge(['class' => 'mb-8 flex items-end justify-between gap-4']) }}>
    <h2 class="relative text-3xl leading-none sm:text-4xl" data-reveal>
        {{ $title }}
        {{-- A marker stroke under the title that draws itself when it scrolls into view. --}}
        <svg class="draw-line absolute -bottom-3 left-0 h-2.5 w-full text-ochre" viewBox="0 0 200 10" preserveAspectRatio="none" aria-hidden="true">
            <path pathLength="1" d="M2 6.5C30 3 62 8.2 96 5.2S160 2.4 198 5.8" fill="none" stroke="currentColor" stroke-width="2.6" stroke-linecap="round" />
        </svg>
    </h2>
    @if ($link)
        <a href="{{ $link }}" class="inline-flex shrink-0 items-center gap-1 text-sm text-ink-soft hover:text-ink">
            {{ $linkText }} <x-glyph name="arrow-right" class="size-4" />
        </a>
    @endif
</div>
