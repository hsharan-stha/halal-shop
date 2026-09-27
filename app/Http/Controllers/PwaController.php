<?php

namespace App\Http\Controllers;

use App\Services\BrandingService;
use Illuminate\Http\JsonResponse;

class PwaController extends Controller
{
    public function manifest(BrandingService $branding): JsonResponse
    {
        $icon = $branding->faviconUrl();

        $icons = $icon
            ? [['src' => $icon, 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'any']]
            : [
                ['src' => asset('icons/icon-192.png'), 'sizes' => '192x192', 'type' => 'image/png', 'purpose' => 'any'],
                ['src' => asset('icons/icon-512.png'), 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'any'],
                ['src' => asset('icons/icon-maskable-512.png'), 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'maskable'],
            ];

        return response()->json([
            'name' => $branding->name(),
            'short_name' => $branding->shortName(),
            'start_url' => route('home', absolute: false).'?source=pwa',
            'scope' => '/',
            'display' => 'standalone',
            'orientation' => 'portrait',
            'lang' => app()->getLocale(),
            'background_color' => settings('branding.background_color'),
            'theme_color' => settings('branding.primary_color'),
            'icons' => $icons,
        ], 200, ['Content-Type' => 'application/manifest+json', 'Cache-Control' => 'public, max-age=3600']);
    }
}
