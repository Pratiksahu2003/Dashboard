<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
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

        // Prefer the API-resolved user (set by SyncApiUser middleware) over local Auth::user().
        $apiUser = $request->attributes->get('api_user');
        $localUser = Auth::user()?->only(['id', 'name', 'first_name', 'last_name', 'email', 'role', 'phone', 'profile_pic', 'email_verified_at', 'registration_fee_status', 'payment_required', 'verification_status']);

        $user = $apiUser ?? $localUser;

        // Whitelist safe fields to prevent leaking sensitive data from the API response.
        $safeFields = ['id', 'name', 'first_name', 'last_name', 'email', 'role', 'phone', 'profile_pic', 'email_verified_at', 'registration_fee_status', 'payment_required', 'verification_status'];
        if (is_array($user)) {
            $user = array_intersect_key($user, array_flip($safeFields));
        }

        return [
            ...parent::share($request),
            'auth' => [
                'user' => $user ?: null,
            ],
            'authSlides' => $authSlides,
            'authSlidesVersion' => $slideVersion,
        ];
    }
}
