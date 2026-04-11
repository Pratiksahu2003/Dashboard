<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureGuest
{
    use ResolvesAuthState;

    public function handle(Request $request, Closure $next): Response
    {
        if ($this->resolveAuthState($request)['authenticated']) {
            return redirect()->route('dashboard');
        }

        return $next($request);
    }
}
