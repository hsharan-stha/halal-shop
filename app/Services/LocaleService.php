<?php

namespace App\Services;

class LocaleService
{
    public function __construct(private SettingsService $settings) {}

    /**
     * Locales supported by the codebase and enabled via feature flags.
     *
     * @return array<string, array{name: string, native: string, hreflang: string}>
     */
    public function enabled(): array
    {
        $flags = ['ja' => 'japanese_enabled', 'en' => 'english_enabled'];

        $enabled = array_filter(
            config('shop.locales'),
            fn (string $locale) => ! isset($flags[$locale]) || $this->settings->feature($flags[$locale]),
            ARRAY_FILTER_USE_KEY,
        );

        return $enabled !== [] ? $enabled : [config('app.locale') => config('shop.locales')[config('app.locale')]];
    }

    public function isEnabled(?string $locale): bool
    {
        return $locale !== null && array_key_exists($locale, $this->enabled());
    }

    public function default(): string
    {
        $default = (string) $this->settings->get('languages.default_locale', config('app.locale'));

        return $this->isEnabled($default) ? $default : (string) array_key_first($this->enabled());
    }
}
