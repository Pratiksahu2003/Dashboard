<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Production-grade security headers.
 * Follows OWASP recommendations and modern browser security standards.
 */
class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);
        $apiOrigin = rtrim(config('services.suganta.api_origin', 'https://api.suganta.com'), '/');
        $cloudflareChallenges = 'https://challenges.cloudflare.com';

        // Prevent MIME-type sniffing
        $response->headers->set('X-Content-Type-Options', 'nosniff');

        // Clickjacking protection
        $response->headers->set('X-Frame-Options', 'SAMEORIGIN');

        // Legacy XSS filter (belt-and-suspenders)
        $response->headers->set('X-XSS-Protection', '1; mode=block');

        // Referrer leakage control
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');

        // Restrict browser feature access
        $response->headers->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=(), payment=()');

        // Content Security Policy
        // Allows: self, Inertia/Vue inline styles, YouTube embeds, Firebase, API domain
        $csp = implode('; ', [
            "default-src 'self'",
            "script-src 'self' 'unsafe-inline' https://www.youtube.com https://www.youtube-nocookie.com {$cloudflareChallenges}",
            "script-src-elem 'self' 'unsafe-inline' https://www.youtube.com https://www.youtube-nocookie.com {$cloudflareChallenges}",
            "style-src 'self' 'unsafe-inline' https://fonts.bunny.net",
            "style-src-elem 'self' 'unsafe-inline' https://fonts.bunny.net",
            "img-src 'self' data: blob: https:",
            "font-src 'self' data: https://fonts.bunny.net",
            "connect-src 'self' {$apiOrigin} {$cloudflareChallenges} wss: ws:",
            // Google Maps embeds use nested *.google.com frames; YouTube stays explicit (youtube.com ≠ *.google.com)
            "frame-src https://*.google.com https://www.youtube.com https://www.youtube-nocookie.com {$cloudflareChallenges}",
            "worker-src 'self' blob:",
            "manifest-src 'self'",
            "base-uri 'self'",
            "form-action 'self'",
            "upgrade-insecure-requests",
        ]);
        $response->headers->set('Content-Security-Policy', $csp);

        // HTTPS enforcement (HSTS with preload), including Cloudflare proxy headers.
        $forwardedProto = strtolower((string) $request->header('X-Forwarded-Proto', ''));
        $cfVisitor = strtolower((string) $request->header('CF-Visitor', ''));
        $isHttpsViaProxy = $forwardedProto === 'https' || str_contains($cfVisitor, '"scheme":"https"');

        if ($request->isSecure() || $isHttpsViaProxy) {
            $response->headers->set(
                'Strict-Transport-Security',
                'max-age=31536000; includeSubDomains; preload'
            );
        }

        // Remove server fingerprinting headers
        $response->headers->remove('X-Powered-By');
        $response->headers->remove('Server');

        return $response;
    }
}
