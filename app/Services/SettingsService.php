<?php

namespace App\Services;

use App\Models\Setting;
use App\Support\Settings\SettingsRegistry;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

class SettingsService
{
    private const CACHE_KEY = 'settings.all';

    /**
     * @var array<string, array<string, mixed>>|null
     */
    private ?array $loaded = null;

    public function __construct(private AuditLogger $auditLogger) {}

    /**
     * Read a setting using "group.key" notation, falling back to the registry default.
     */
    public function get(string $path, mixed $default = null): mixed
    {
        [$group, $key] = array_pad(explode('.', $path, 2), 2, null);

        $stored = $this->all()[$group][$key] ?? null;

        if ($stored !== null) {
            return $stored;
        }

        $field = SettingsRegistry::field((string) $group, (string) $key);

        return $field['default'] ?? $default;
    }

    /**
     * Resolve a translatable setting for the current (or given) locale.
     */
    public function translated(string $path, ?string $locale = null): string
    {
        $value = $this->get($path);

        if (! is_array($value)) {
            return (string) $value;
        }

        $locale ??= app()->getLocale();

        return (string) ($value[$locale] ?? '') ?: (string) ($value[config('app.fallback_locale')] ?? '');
    }

    public function feature(string $flag): bool
    {
        return (bool) $this->get('features.'.$flag, false);
    }

    /**
     * Every group merged with registry defaults.
     *
     * @return array<string, mixed>
     */
    public function group(string $group): array
    {
        $values = [];

        foreach (SettingsRegistry::groups()[$group] ?? [] as $key => $field) {
            $values[$key] = $this->get($group.'.'.$key);
        }

        return $values;
    }

    /**
     * Persist validated values for a group. Only keys declared in the registry are stored.
     *
     * @param  array<string, mixed>  $input
     */
    public function update(string $group, array $input): void
    {
        $fields = SettingsRegistry::groups()[$group] ?? [];
        $old = $this->group($group);
        $new = [];

        foreach ($fields as $key => $field) {
            if ($field['type'] === 'image') {
                $value = $this->resolveImage($group, $key, $input, $old[$key] ?? null);
            } elseif ($field['type'] === 'boolean') {
                $value = (bool) ($input[$key] ?? false);
            } elseif (! array_key_exists($key, $input)) {
                continue;
            } else {
                $value = $this->normalize($field['type'], $input[$key]);
            }

            Setting::query()->updateOrCreate(['group' => $group, 'key' => $key], ['value' => $value]);
            $new[$key] = $value;
        }

        $this->flush();

        $changedOld = array_intersect_key($old, $new);
        $changes = array_filter($new, fn ($value, $key) => ($changedOld[$key] ?? null) !== $value, ARRAY_FILTER_USE_BOTH);

        if ($changes !== []) {
            $this->auditLogger->log('settings.updated', null, array_intersect_key($changedOld, $changes) + ['group' => $group], $changes + ['group' => $group]);
        }
    }

    public function set(string $group, string $key, mixed $value): void
    {
        Setting::query()->updateOrCreate(['group' => $group, 'key' => $key], ['value' => $value]);
        $this->flush();
    }

    public function flush(): void
    {
        Cache::forget(self::CACHE_KEY);
        $this->loaded = null;
    }

    public function mediaUrl(?string $path): ?string
    {
        return $path ? Storage::disk(config('shop.media_disk'))->url($path) : null;
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public function all(): array
    {
        if ($this->loaded !== null) {
            return $this->loaded;
        }

        try {
            return $this->loaded = Cache::rememberForever(self::CACHE_KEY, function (): array {
                if (! Schema::hasTable('settings')) {
                    return [];
                }

                $grouped = [];

                foreach (Setting::query()->get(['group', 'key', 'value']) as $setting) {
                    $grouped[$setting->group][$setting->key] = $setting->value;
                }

                return $grouped;
            });
        } catch (Throwable) {
            return $this->loaded = [];
        }
    }

    private function normalize(string $type, mixed $value): mixed
    {
        return match ($type) {
            'integer' => (int) $value,
            'int_list' => collect(explode(',', (string) $value))
                ->map(fn ($day) => (int) trim($day))
                ->filter(fn (int $day) => $day > 0)
                ->unique()
                ->sortDesc()
                ->values()
                ->all(),
            'translatable_string', 'translatable_text' => collect(array_keys(config('shop.locales')))
                ->mapWithKeys(fn (string $locale) => [$locale => trim((string) (($value ?? [])[$locale] ?? ''))])
                ->all(),
            'color' => strtolower((string) $value),
            default => $value === null ? '' : trim((string) $value),
        };
    }

    /**
     * @param  array<string, mixed>  $input
     */
    private function resolveImage(string $group, string $key, array $input, ?string $current): ?string
    {
        $disk = Storage::disk(config('shop.media_disk'));
        $file = $input[$key] ?? null;

        if ($file instanceof UploadedFile) {
            $path = $file->storeAs('settings', $group.'-'.$key.'-'.Str::random(24).'.'.$file->extension(), config('shop.media_disk'));

            if ($current) {
                $disk->delete($current);
            }

            return $path;
        }

        if (! empty($input[$key.'_remove']) && $current) {
            $disk->delete($current);

            return null;
        }

        return $current;
    }
}
