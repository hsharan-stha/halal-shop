<?php

namespace App\Http\Middleware;

use App\Services\LocaleService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Resolves the request locale: explicit API header, session choice,
 * authenticated user preference, browser preference, then store default.
 */
class SetLocale
{
    public function __construct(private LocaleService $locales) {}

    public function handle(Request $request, Closure $next): Response
    {
        $candidates = [
            $request->is('api/*') ? $request->header('X-Locale') : null,
            $request->hasSession() ? $request->session()->get('locale') : null,
            $request->user()?->locale,
            $request->getPreferredLanguage(array_keys($this->locales->enabled())),
        ];

        $locale = $this->locales->default();

        foreach ($candidates as $candidate) {
            if ($this->locales->isEnabled($candidate)) {
                $locale = $candidate;
                break;
            }
        }

        app()->setLocale($locale);

        return $next($request);
    }
}
