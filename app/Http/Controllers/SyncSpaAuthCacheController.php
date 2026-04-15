<?php

namespace App\Http\Controllers;

use App\Http\Middleware\EnsureAuthenticated;
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

        $redirectTo = $this->normalizeInternalRedirect($request->input('redirect_to'));

        if ($redirectTo !== null) {
            return redirect()->to($redirectTo);
        }

        return redirect()->route('dashboard');
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
