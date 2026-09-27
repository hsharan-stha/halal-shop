<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SecurityHeaders
{
    /**
     * Path prefixes that must never be indexed by search engines.
     */
    private const NO_INDEX = ['admin', 'admin/*', 'account', 'account/*', 'cart', 'checkout', 'checkout/*', 'login', 'register', 'password/*', 'email/*', 'api/*'];

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('X-Frame-Options', 'SAMEORIGIN');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->headers->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=(), payment=(self)');

        if ($request->isSecure()) {
            $response->headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        }

        if ($request->is(...self::NO_INDEX)) {
            $response->headers->set('X-Robots-Tag', 'noindex, nofollow');
        }

        if ($request->user() && ! $request->is('api/*')) {
            $response->headers->set('Cache-Control', 'no-store, private');
        }

        return $response;
    }
}
