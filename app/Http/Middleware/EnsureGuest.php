<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class EnsureGuest
{
    use ResolvesAuthState;

    public function handle(Request $request, Closure $next): Response
    {
        if ($this->resolveAuthState($request, false)['authenticated']) {
            $redirect = $this->sanitizeRedirectTarget((string) $request->query('redirect', ''));
            if ($redirect !== '') {
                return redirect()->to($redirect);
            }
            return redirect()->route('dashboard');
        }

        return $next($request);
    }

    private function sanitizeRedirectTarget(string $candidate): string
    {
        $candidate = trim($candidate);
        if ($candidate === '') {
            return '';
        }

        if (str_starts_with($candidate, '/') && ! str_starts_with($candidate, '//')) {
            return $candidate;
        }

        try {
            $target = parse_url($candidate);
            $host = Str::lower((string) ($target['host'] ?? ''));
            if ($host === '') {
                return '';
            }

            if ($host === 'suganta.com' || str_ends_with($host, '.suganta.com')) {
                return $candidate;
            }
        } catch (\Throwable) {
            return '';
        }

        return '';
    }
}
