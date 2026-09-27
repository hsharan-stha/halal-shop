<?php

namespace App\Support;

use Illuminate\Support\Str;

final class PermissionCatalog
{
    /**
     * @var array<string, true>|null
     */
    private static ?array $index = null;

    /**
     * @return array<string, list<string>>
     */
    public static function grouped(): array
    {
        return config('permissions.permissions');
    }

    /**
     * @return list<string>
     */
    public static function all(): array
    {
        return array_merge(...array_values(self::grouped()));
    }

    public static function exists(string $permission): bool
    {
        self::$index ??= array_fill_keys(self::all(), true);

        return isset(self::$index[$permission]);
    }

    /**
     * Permission slugs contain dots, so they cannot be resolved as nested translation keys.
     */
    public static function label(string $permission): string
    {
        $labels = trans('admin.permissions.items');

        return is_array($labels) ? ($labels[$permission] ?? $permission) : $permission;
    }

    /**
     * Expand a role's grant patterns ("orders.*", "*") into concrete permissions.
     *
     * @param  list<string>  $patterns
     * @return list<string>
     */
    public static function expand(array $patterns): array
    {
        return array_values(array_filter(
            self::all(),
            fn (string $permission) => collect($patterns)->contains(fn (string $pattern) => Str::is($pattern, $permission)),
        ));
    }
}
