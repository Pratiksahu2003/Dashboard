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

        $user = $request->attributes->get('api_user');

        return [
            ...parent::share($request),
            'auth' => ['user' => $user],
            'authSlides' => $authSlides,
            'authSlidesVersion' => $slideVersion,
        ];
    }
}
