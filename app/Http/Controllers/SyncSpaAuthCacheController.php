<?php

namespace App\Http\Controllers;

use App\Http\Middleware\EnsureAuthenticated;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Http\Request;

/**
 * Clears cached SPA auth resolution so the next request (e.g. dashboard) re-fetches /auth/user.
 * Call after API login/OTP success so Laravel does not reuse a stale "unauthenticated" cache entry.
 */
class SyncSpaAuthCacheController extends Controller
{
    public function __invoke(Request $request)
    {
        EnsureAuthenticated::forgetSpaAuthCacheForRequest($request);
        $this->queueBearerTokenCookie($request->input('token'));

        $redirectTo = $this->normalizeInternalRedirect($request->input('redirect_to'));

        if ($redirectTo !== null) {
            return redirect()->to($redirectTo);
        }

        return redirect()->route('dashboard');
    }

    private function queueBearerTokenCookie(mixed $token): void
    {
        if (! is_string($token)) {
            return;
        }

        $token = trim($token);
        if ($token === '' || strlen($token) > 4096) {
            return;
        }

        $secure = config('session.secure');

        Cookie::queue(cookie(
            name: EnsureAuthenticated::BEARER_TOKEN_COOKIE,
            value: $token,
            minutes: 60 * 24 * 30,
            path: '/',
            domain: config('session.domain'),
            secure: is_bool($secure) ? $secure : request()->isSecure(),
            httpOnly: true,
            sameSite: 'Lax',
        ));
    }

    private function normalizeInternalRedirect(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $candidate = trim($value);
        if ($candidate === '' || ! str_starts_with($candidate, '/') || str_starts_with($candidate, '//')) {
            return null;
        }

        return $candidate;
    }
}
