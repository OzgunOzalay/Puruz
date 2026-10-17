<?php

namespace App\Filament\Support;

use Illuminate\Support\HtmlString;

/** The one-line summary shown under a list page's title, e.g. "14 parça satışta · 2 taslak". */
final class PageSummary
{
    /** @param list<array{0: ?string, 1?: ?string}> $parts bold lead text and optional plain rest */
    public static function make(array $parts): HtmlString
    {
        $html = collect($parts)
            ->filter(fn (array $part) => filled($part[0] ?? null))
            ->map(fn (array $part) => '<strong class="font-semibold text-gray-950 dark:text-white">'.e($part[0]).'</strong>'
                .(filled($part[1] ?? null) ? ' '.e($part[1]) : ''))
            ->implode(' <span class="text-gray-400 dark:text-gray-500">·</span> ');

        return new HtmlString('<span class="text-sm text-gray-600 dark:text-gray-400">'.$html.'</span>');
    }
}
