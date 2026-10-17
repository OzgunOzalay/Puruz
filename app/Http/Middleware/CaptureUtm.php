<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Remembers where a visitor came from (e.g. ?utm_source=instagram&utm_medium=story) so the
 * order can be attributed to an Instagram placement in the admin reports.
 */
class CaptureUtm
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->isMethod('GET') && $request->hasSession() && $request->filled('utm_source')) {
            $request->session()->put('utm', [
                'utm_source' => mb_substr((string) $request->query('utm_source'), 0, 100),
                'utm_medium' => mb_substr((string) $request->query('utm_medium', ''), 0, 100) ?: null,
                'utm_campaign' => mb_substr((string) $request->query('utm_campaign', ''), 0, 100) ?: null,
            ]);
        } elseif ($request->isMethod('GET') && $request->hasSession() && ! $request->session()->has('utm')) {
            // Instagram's in-app browser strips UTM tags from some links but keeps its referrer.
            $referer = (string) $request->headers->get('referer');
            if (str_contains($referer, 'instagram.com') || str_contains((string) $request->userAgent(), 'Instagram')) {
                $request->session()->put('utm', ['utm_source' => 'instagram', 'utm_medium' => null, 'utm_campaign' => null]);
            }
        }

        return $next($request);
    }
}
