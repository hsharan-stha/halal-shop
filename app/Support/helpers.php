<?php

use App\Services\SettingsService;
use App\Support\Money;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;

if (! function_exists('settings')) {
    function settings(?string $path = null, mixed $default = null): mixed
    {
        $service = app(SettingsService::class);

        return $path === null ? $service : $service->get($path, $default);
    }
}

if (! function_exists('feature')) {
    function feature(string $flag): bool
    {
        return app(SettingsService::class)->feature($flag);
    }
}

if (! function_exists('money')) {
    function money(?int $amount, ?string $currency = null): string
    {
        return Money::format((int) $amount, $currency);
    }
}

if (! function_exists('local_time')) {
    /**
     * Convert a stored (UTC) timestamp to the store's display timezone.
     */
    function local_time(CarbonInterface|string|null $value): ?Carbon
    {
        if ($value === null) {
            return null;
        }

        return Carbon::parse($value)->setTimezone(config('app.display_timezone'));
    }
}

if (! function_exists('local_today')) {
    /**
     * Start of the current calendar day in the store's display timezone.
     */
    function local_today(): Carbon
    {
        return Carbon::now(config('app.display_timezone'))->startOfDay();
    }
}

if (! function_exists('local_time_input')) {
    /**
     * Parse a form value entered in the store's display timezone and return it in UTC.
     */
    function local_time_input(?string $value): ?Carbon
    {
        return filled($value) ? Carbon::parse($value, config('app.display_timezone'))->utc() : null;
    }
}

if (! function_exists('country_name')) {
    /**
     * Localised country name for an ISO 3166-1 alpha-2 code.
     */
    function country_name(?string $code): string
    {
        if (blank($code)) {
            return '';
        }

        $code = strtoupper($code);

        if (class_exists(Locale::class)) {
            $name = Locale::getDisplayRegion('-'.$code, app()->getLocale());

            if ($name !== '' && $name !== $code) {
                return $name;
            }
        }

        return $code;
    }
}

if (! function_exists('local_date')) {
    function local_date(CarbonInterface|string|null $value, bool $withTime = false): string
    {
        $time = local_time($value);

        if (! $time) {
            return '';
        }

        $format = app()->getLocale() === 'ja'
            ? ($withTime ? 'Y年n月j日 H:i' : 'Y年n月j日')
            : ($withTime ? 'M j, Y H:i' : 'M j, Y');

        return $time->format($format);
    }
}
