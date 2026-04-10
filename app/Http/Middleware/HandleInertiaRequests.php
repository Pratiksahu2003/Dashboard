<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that is loaded on the first page visit.
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determine the current asset version.
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $slideVersion = (string) config('auth_slides.version', '1');
        $slideTtl = (int) config('auth_slides.cache_ttl', 86400);
        $cacheKey = 'inertia:auth_slides:manifest:v'.$slideVersion;

        $authSlides = Cache::remember($cacheKey, $slideTtl, function () {
            return config('auth_slides.items', []);
        });

        return [
            ...parent::share($request),
            'auth' => [
                'user' => null, // Stateless: Frontend handles user state via tokens
            ],
            'authSlides' => $authSlides,
            'authSlidesVersion' => $slideVersion,
        ];
    }
}
