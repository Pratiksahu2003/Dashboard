<?php

namespace App\Providers;

use Illuminate\Support\Facades\Vite;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void {}

    public function boot(): void
    {
        // Prefetch only the critical app chunk and CSS — not all vendor chunks.
        // Vendor chunks (quill, firebase, echo) are lazy-loaded on demand.
        Vite::prefetch(concurrency: 3);
    }
}
