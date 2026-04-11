<?php

namespace App\Http\Support;

use Illuminate\Http\Request;

/**
 * Headers for server-side HTTP calls to the SuGanta API that must behave like the browser
 * (session cookies encrypted for the API; Origin/Referer for stateful resolution).
 */
final class SugantaBrowserProxyHeaders
{
    /**
     * Prefer the raw Cookie header so encrypted API session cookies are forwarded unchanged.
     */
    public static function cookieLine(Request $request): ?string
    {
        $raw = $request->headers->get('Cookie');
        if (is_string($raw) && $raw !== '') {
            return $raw;
        }

        $pairs = [];
        foreach ($request->cookies->all() as $name => $value) {
            if (! is_string($name) || $name === '') {
                continue;
            }
            if (! is_scalar($value) || (string) $value === '') {
                continue;
            }
            $pairs[] = $name . '=' . (string) $value;
        }

        return $pairs === [] ? null : implode('; ', $pairs);
    }

    /**
     * @return array<string, string>
     */
    public static function spa(Request $request): array
    {
        $headers = [];
        $appBase = rtrim((string) config('app.url'), '/');

        $origin = $request->headers->get('Origin');
        if ((! is_string($origin) || $origin === '') && $appBase !== '') {
            $origin = $appBase;
        }
        if (is_string($origin) && $origin !== '') {
            $headers['Origin'] = $origin;
        }

        $referer = $request->headers->get('Referer');
        if ((! is_string($referer) || $referer === '') && $appBase !== '') {
            $uri = $request->getRequestUri();
            $referer = $appBase . (is_string($uri) && $uri !== '' ? $uri : '/');
        }
        if (is_string($referer) && $referer !== '') {
            $headers['Referer'] = $referer;
        }

        $ua = $request->userAgent();
        if (is_string($ua) && $ua !== '') {
            $headers['User-Agent'] = $ua;
        }

        return $headers;
    }

    /**
     * @return array<string, string>
     */
    public static function forJsonApi(Request $request, bool $withJsonContentType = true): array
    {
        $headers = [
            'Accept' => 'application/json',
            'X-Requested-With' => 'XMLHttpRequest',
        ];
        if ($withJsonContentType) {
            $headers['Content-Type'] = 'application/json';
        }
        $headers = array_merge($headers, self::spa($request));
        $cookie = self::cookieLine($request);
        if ($cookie !== null && $cookie !== '') {
            $headers['Cookie'] = $cookie;
        }

        return $headers;
    }
}
