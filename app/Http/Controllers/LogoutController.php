<?php

namespace App\Http\Controllers;

use App\Http\Middleware\EnsureAuthenticated;
use App\Http\Support\SugantaBrowserProxyHeaders;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Clears server-side SPA auth cache, proxies API session logout (cookies), then redirects to login.
 * Must not use EnsureAuthenticated middleware: API logout invalidates the session this app checks.
 */
class LogoutController extends Controller
{
    public function __invoke(Request $request)
    {
        EnsureAuthenticated::forgetSpaAuthCacheForRequest($request);
        Cookie::queue(Cookie::forget(EnsureAuthenticated::BEARER_TOKEN_COOKIE, '/', config('session.domain')));

        $apiOrigin = rtrim((string) config('services.suganta.api_origin', ''), '/');
        if ($apiOrigin !== '') {
            $url = $apiOrigin . '/api/v1/auth/logout';
            try {
                $headers = SugantaBrowserProxyHeaders::forJsonApi($request);
                $bearerToken = $request->cookie(EnsureAuthenticated::BEARER_TOKEN_COOKIE);
                if (is_string($bearerToken) && $bearerToken !== '') {
                    $headers['Authorization'] = 'Bearer ' . $bearerToken;
                }

                Http::timeout(12)
                    ->withHeaders($headers)
                    ->post($url, []);
            } catch (\Exception $e) {
                Log::debug('API logout proxy failed', [
                    'message' => $e->getMessage(),
                ]);
            }
        }

        return redirect()
            ->route('login')
            ->with('status', 'You have been logged out successfully.');
    }
}
