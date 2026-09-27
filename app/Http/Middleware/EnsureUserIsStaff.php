<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserIsStaff
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user) {
            return $request->expectsJson()
                ? response()->json(['success' => false, 'message' => __('auth.unauthenticated')], 401)
                : redirect()->guest(route('admin.login'));
        }

        abort_unless($user->isStaff() && $user->isActive(), 403);

        return $next($request);
    }
}
