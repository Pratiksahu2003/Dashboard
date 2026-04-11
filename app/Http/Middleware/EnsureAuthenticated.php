<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureAuthenticated
{
    use ResolvesAuthState;

    public function handle(Request $request, Closure $next): Response
    {
        $authState = $this->resolveAuthState($request);

        if (! $authState['authenticated']) {
            return redirect()->route('login');
        }

        $request->attributes->set('api_user', $authState['user']);

        return $next($request);
    }
}
