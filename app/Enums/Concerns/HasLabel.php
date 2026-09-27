<?php

namespace App\Enums\Concerns;

use Illuminate\Support\Str;

trait HasLabel
{
    /**
     * Translated, human readable label for the case.
     */
    public function label(): string
    {
        return __('enums.'.Str::snake(class_basename(static::class)).'.'.$this->value);
    }

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        $options = [];

        foreach (self::cases() as $case) {
            $options[$case->value] = $case->label();
        }

        return $options;
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(fn (self $case) => $case->value, self::cases());
    }
}
