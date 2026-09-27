<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

/**
 * Operational checks for the admin health page. Never returns credentials.
 */
class SystemHealthService
{
    /**
     * @return list<array{key: string, ok: bool, detail: string}>
     */
    public function checks(): array
    {
        return [
            $this->check('database', function (): string {
                DB::select('select 1');

                return DB::connection()->getDriverName();
            }),
            $this->check('cache', function (): string {
                $key = 'health:'.Str::random(8);
                Cache::put($key, 'ok', 10);
                $ok = Cache::pull($key) === 'ok';

                return $ok ? config('cache.default') : throw new \RuntimeException('Cache read/write mismatch');
            }),
            $this->check('queue', function (): string {
                $driver = config('queue.default');

                if ($driver === 'database' && Schema::hasTable('jobs')) {
                    $pending = DB::table('jobs')->count();
                    $failed = Schema::hasTable('failed_jobs') ? DB::table('failed_jobs')->count() : 0;

                    return __('admin.health.queue_detail', ['driver' => $driver, 'pending' => $pending, 'failed' => $failed]);
                }

                return $driver;
            }),
            $this->check('storage', function (): string {
                $disk = Storage::disk(config('shop.media_disk'));
                $path = 'health/'.Str::random(12).'.txt';
                $disk->put($path, 'ok');
                $disk->delete($path);

                return config('shop.media_disk').' / '.config('shop.private_disk');
            }),
            $this->check('mail', function (): string {
                $mailer = (string) config('mail.default');

                if (in_array($mailer, ['log', 'array'], true) && app()->isProduction()) {
                    throw new \RuntimeException(__('admin.health.mail_not_configured', ['mailer' => $mailer]));
                }

                return $mailer.' · '.config('mail.from.address');
            }),
            $this->check('application', fn (): string => implode(' · ', [
                'Laravel '.app()->version(),
                'PHP '.PHP_VERSION,
                app()->environment(),
                config('app.debug') ? 'debug=on' : 'debug=off',
                app()->configurationIsCached() ? 'config cached' : 'config not cached',
            ])),
        ];
    }

    /**
     * @return array{key: string, ok: bool, detail: string}
     */
    private function check(string $key, callable $callback): array
    {
        try {
            return ['key' => $key, 'ok' => true, 'detail' => (string) $callback()];
        } catch (Throwable $e) {
            report($e);

            return ['key' => $key, 'ok' => false, 'detail' => __('admin.health.failed', ['type' => class_basename($e)])];
        }
    }
}
