@php($next = $workshop->upcomingSessions->first(fn ($s) => $s->isBookable()))
<a href="{{ $workshop->url() }}" class="group flex flex-col overflow-hidden rounded-[1.75rem] border border-line bg-paper">
    <div class="relative aspect-[4/3] overflow-hidden bg-paper-deep">
        @if ($workshop->coverImage())
            <img src="{{ $workshop->coverUrl(800) }}" alt="{{ $workshop->name }}" loading="lazy" class="size-full object-cover transition duration-500 group-hover:scale-[1.03]">
        @endif
    </div>
    <div class="flex flex-1 flex-col p-5">
        <h3 class="text-3xl lowercase leading-tight">{{ $workshop->name }}</h3>
        @if ($workshop->short_description)
            <p class="mt-2 line-clamp-2 text-[15px] text-ink-soft">{{ $workshop->short_description }}</p>
        @endif
        <div class="mt-auto flex items-end justify-between gap-3 pt-5">
            <div class="text-sm">
                @if ($next)
                    <p class="flex items-center gap-1.5 text-ink"><x-glyph name="calendar" class="size-4" />{{ $next->starts_at->translatedFormat('j F l, H:i') }}</p>
                    <p class="mt-1 text-ink-soft">{{ $next->seatsLeft() }} kişilik yer kaldı</p>
                @else
                    <p class="text-ink-soft">Yeni tarihler yakında</p>
                @endif
            </div>
            <x-shop.price :product="$workshop" class="shrink-0" />
        </div>
    </div>
</a>
