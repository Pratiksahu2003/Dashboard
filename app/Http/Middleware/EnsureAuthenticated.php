<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class EnsureAuthenticated
{
    use ResolvesAuthState;

    public function handle(Request $request, Closure $next): Response
    {
        $authState = $this->resolveAuthState($request);

        if (! $authState['authenticated']) {
            $message = $this->resolveLoginMessage($request);

            return redirect()->route('login', [
                'redirect' => $request->fullUrl(),
                'message' => $message,
            ]);
        }

        $request->attributes->set('api_user', $authState['user']);

        return $next($request);
    }

    private function resolveLoginMessage(Request $request): string
    {
        $fromQuery = trim((string) $request->query('message', ''));
        if ($fromQuery !== '') {
            return $fromQuery;
        }

        $host = Str::lower((string) $request->getHost());
        if ($host === 'ai.suganta.com') {
            return 'Login To Access SuGanta Ai';
        }

        return 'Please login to continue.';
    }
}
