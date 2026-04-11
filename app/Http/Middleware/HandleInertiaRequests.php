<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    protected $rootView = 'app';

    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    public function share(Request $request): array
    {
        $slideVersion = (string) config('auth_slides.version', '1');
        $cacheKey     = 'inertia:auth_slides:v' . $slideVersion;

        $authSlides = Cache::remember(
            $cacheKey,
            (int) config('auth_slides.cache_ttl', 86400),
            fn () => config('auth_slides.items', [])
        );

        // Inertia runs this middleware before route middleware, so `api_user` is not set yet.
        // Use a closure so the user is resolved when the response is built (after EnsureAuthenticated).
        return [
            ...parent::share($request),
            'auth' => [
                'user' => fn () => $request->attributes->get('api_user'),
            ],
            'authSlides' => $authSlides,
            'authSlidesVersion' => $slideVersion,
        ];
    }
}
