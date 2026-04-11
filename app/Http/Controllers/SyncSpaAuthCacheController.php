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

        return redirect()->route('dashboard');
    }
}
