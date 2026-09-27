<?php

namespace App\Http\Requests\Concerns;

trait HandlesTranslations
{
    /**
     * Validation rules for a {"ja": ..., "en": ...} input.
     *
     * @return array<string, list<string>>
     */
    protected function translationRules(string $field, int $max, bool $requireJapanese = false): array
    {
        $rules = [$field => ['nullable', 'array']];

        foreach (array_keys(config('shop.locales')) as $locale) {
            $rules[$field.'.'.$locale] = [$requireJapanese && $locale === 'ja' ? 'required' : 'nullable', 'string', 'max:'.$max];
        }

        return $rules;
    }

    /**
     * @return array<string, string>|null
     */
    protected function translation(string $field): ?array
    {
        $values = [];

        foreach (array_keys(config('shop.locales')) as $locale) {
            $value = trim((string) data_get($this->validated(), $field.'.'.$locale, ''));

            if ($value !== '') {
                $values[$locale] = $value;
            }
        }

        return $values === [] ? null : $values;
    }
}
