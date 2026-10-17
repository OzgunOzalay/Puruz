<?php

namespace App\Support;

use App\Models\Order;
use Illuminate\Support\Str;

/** Builds an iCalendar file with the workshop sessions of an order. */
class Ics
{
    public static function forOrder(Order $order): ?string
    {
        $order->loadMissing('items.session');
        $sessions = $order->items->pluck('session')->filter()->unique('id');

        if ($sessions->isEmpty()) {
            return null;
        }

        $shop = Settings::get('shop_name');
        $lines = [
            'BEGIN:VCALENDAR',
            'VERSION:2.0',
            'PRODID:-//'.$shop.'//Atolye//TR',
            'CALSCALE:GREGORIAN',
            'METHOD:PUBLISH',
        ];

        foreach ($sessions as $session) {
            $item = $order->items->firstWhere('workshop_session_id', $session->id);
            $lines = array_merge($lines, [
                'BEGIN:VEVENT',
                'UID:'.$order->number.'-'.$session->id.'@'.parse_url(config('app.url'), PHP_URL_HOST),
                'DTSTAMP:'.now()->utc()->format('Ymd\THis\Z'),
                'DTSTART:'.$session->starts_at->copy()->utc()->format('Ymd\THis\Z'),
                'DTEND:'.$session->ends_at->copy()->utc()->format('Ymd\THis\Z'),
                'SUMMARY:'.self::escape($item->name.' – '.$shop),
                'DESCRIPTION:'.self::escape('Sipariş '.$order->number.'. '.($session->schedule_note ?? '')),
                'LOCATION:'.self::escape((string) Settings::get('address')),
                'END:VEVENT',
            ]);
        }

        $lines[] = 'END:VCALENDAR';

        return implode("\r\n", array_map(fn ($line) => Str::limit($line, 600, ''), $lines))."\r\n";
    }

    private static function escape(string $value): string
    {
        return str_replace(['\\', ';', ',', "\n"], ['\\\\', '\;', '\,', '\n'], $value);
    }
}
