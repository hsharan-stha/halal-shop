<?php

namespace App\Models\Concerns;

/**
 * Helpers for JSON columns shaped as {"ja": "...", "en": "..."} and for
 * models that store an English `name` next to a `japanese_name`.
 */
trait HasTranslations
{
    public function translate(string $attribute, ?string $locale = null): ?string
    {
        $values = $this->getAttribute($attribute);

        if (! is_array($values)) {
            return filled($values) ? (string) $values : null;
        }

        $locale ??= app()->getLocale();

        foreach ([$locale, config('app.fallback_locale'), config('app.locale')] as $candidate) {
            if (filled($values[$candidate] ?? null)) {
                return $values[$candidate];
            }
        }

        $first = collect($values)->first(fn ($value) => filled($value));

        return $first === null ? null : (string) $first;
    }

    public function localizedName(?string $locale = null): string
    {
        $locale ??= app()->getLocale();

        if ($locale === 'ja' && filled($this->japanese_name ?? null)) {
            return $this->japanese_name;
        }

        return (string) $this->name;
    }
}
