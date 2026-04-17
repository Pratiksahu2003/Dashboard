<?php

namespace App\Providers;

use Illuminate\Support\Facades\Vite;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void {}

    public function boot(): void
    {
        // Keep prefetch opt-in. In production behind Cloudflare this can trigger
        // unnecessary burst asset requests and "preload not used" warnings.
        if (filter_var(env('VITE_ASSET_PREFETCH', false), FILTER_VALIDATE_BOOL)) {
            Vite::prefetch(concurrency: 3);
        }
    }
}
