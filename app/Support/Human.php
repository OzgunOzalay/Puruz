<?php

namespace App\Support;

use Carbon\CarbonInterface;

/** Short Turkish phrases for dates and durations, as the admin panel shows them. */
class Human
{
    public static function days(CarbonInterface $from, ?CarbonInterface $to = null): int
    {
        $to ??= now();

        return max(0, (int) $from->copy()->startOfDay()->diffInDays($to->copy()->startOfDay()));
    }

    public static function since(CarbonInterface $from): string
    {
        $days = self::days($from);

        return $days === 0 ? 'bugünden beri' : $days.' gündür';
    }

    public static function took(CarbonInterface $from, CarbonInterface $to): string
    {
        $days = self::days($from, $to);

        return $days === 0 ? 'aynı gün' : $days.' günde';
    }

    public static function day(CarbonInterface $date): string
    {
        $date = $date->copy()->timezone(config('app.timezone'));

        return match (true) {
            $date->isToday() => 'bugün',
            $date->isYesterday() => 'dün',
            $date->isSameYear(now()) => $date->translatedFormat('j M'),
            default => $date->translatedFormat('j M Y'),
        };
    }

    public static function date(CarbonInterface $date): string
    {
        $date = $date->copy()->timezone(config('app.timezone'));

        return $date->isToday() || $date->isYesterday()
            ? self::day($date).' '.$date->format('H:i')
            : self::day($date);
    }
}
