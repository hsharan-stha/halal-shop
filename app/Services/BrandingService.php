<?php

namespace App\Services;

class BrandingService
{
    public function __construct(private SettingsService $settings) {}

    public function name(): string
    {
        return (string) $this->settings->get('branding.application_name') ?: (string) config('app.name');
    }

    public function shortName(): string
    {
        return (string) $this->settings->get('branding.application_short_name') ?: $this->name();
    }

    public function logoUrl(): ?string
    {
        return $this->settings->mediaUrl($this->settings->get('branding.logo'));
    }

    public function faviconUrl(): ?string
    {
        return $this->settings->mediaUrl($this->settings->get('branding.favicon'));
    }

    public function fontFamily(): ?string
    {
        $font = trim((string) $this->settings->get('branding.font_family'));

        return $font !== '' ? $font : null;
    }

    public function darkModeEnabled(): bool
    {
        return (bool) $this->settings->get('branding.dark_mode_enabled');
    }

    /**
     * CSS custom properties consumed by the Tailwind theme (see resources/css/app.css).
     */
    public function cssVariables(): string
    {
        $vars = [
            '--brand-primary' => $this->color('primary_color'),
            '--brand-secondary' => $this->color('secondary_color'),
            '--brand-accent' => $this->color('accent_color'),
            '--brand-background' => $this->color('background_color'),
        ];

        if ($font = $this->fontFamily()) {
            $vars['--brand-font'] = "'".$font."'";
        }

        return collect($vars)->map(fn (string $value, string $name) => $name.':'.$value)->implode(';');
    }

    /**
     * @return array<string, string|null>
     */
    public function socialLinks(): array
    {
        return array_filter([
            'facebook' => $this->settings->get('branding.facebook_url'),
            'instagram' => $this->settings->get('branding.instagram_url'),
            'youtube' => $this->settings->get('branding.youtube_url'),
            'google' => $this->settings->get('branding.google_business_url'),
        ]);
    }

    private function color(string $key): string
    {
        $value = (string) $this->settings->get('branding.'.$key);

        return preg_match('/^#[0-9a-fA-F]{6}$/', $value) ? $value : '#000000';
    }
}
