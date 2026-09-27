<?php

namespace App\Http\Middleware;

use App\Services\SettingsService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Store-level maintenance mode managed from the admin panel. Staff can still
 * browse the storefront; the admin panel and authentication stay reachable.
 */
class EnsureStoreIsAvailable
{
    public function __construct(private SettingsService $settings) {}

    public function handle(Request $request, Closure $next): Response
    {
        $closed = $this->settings->get('maintenance.enabled') || ! $this->settings->feature('online_store_enabled');

        if (! $closed || $request->user()?->isStaff()) {
            return $next($request);
        }

        $message = $this->settings->translated('maintenance.message') ?: __('shop.maintenance.default_message');

        if ($request->is('api/*') || $request->expectsJson()) {
            return response()->json(['success' => false, 'message' => $message, 'errors' => ['maintenance' => true]], 503);
        }

        return response()->view('errors.store-maintenance', ['message' => $message], 503)
            ->header('Retry-After', '3600');
    }
}
