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

            if ($this->isAllowedRedirectHost($host)) {
                return $candidate;
            }
        } catch (\Throwable) {
            return '';
        }

        return '';
    }

    private function isAllowedRedirectHost(string $host): bool
    {
        $host = Str::lower(trim($host));
        if ($host === '') {
            return false;
        }

        $currentHost = Str::lower((string) request()->getHost());
        if ($currentHost !== '' && $host === $currentHost) {
            return true;
        }

        if ($host === 'suganta.com' || str_ends_with($host, '.suganta.com')) {
            return true;
        }

        $configured = config('services.suganta.redirect_allowed_hosts', []);
        if (! is_array($configured)) {
            $configured = [];
        }

        foreach ($configured as $allowed) {
            $allowed = Str::lower(trim((string) $allowed));
            if ($allowed === '') {
                continue;
            }
            if ($host === $allowed || str_ends_with($host, '.' . $allowed)) {
                return true;
            }
        }

        return false;
    }
}
