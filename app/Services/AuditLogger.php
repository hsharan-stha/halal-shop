<?php

namespace App\Services;

use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class AuditLogger
{
    /**
     * Attributes that must never be written to the audit trail.
     */
    private const REDACTED = ['password', 'remember_token', 'two_factor_secret', 'api_token'];

    /**
     * @param  array<string, mixed>|null  $old
     * @param  array<string, mixed>|null  $new
     */
    public function log(string $action, ?Model $subject = null, ?array $old = null, ?array $new = null, ?int $actorId = null): AuditLog
    {
        $request = app()->runningInConsole() && ! app()->runningUnitTests() ? null : request();

        $entry = AuditLog::query()->create([
            'user_id' => $actorId ?? Auth::id(),
            'action' => $action,
            'auditable_type' => $subject?->getMorphClass(),
            'auditable_id' => $subject?->getKey(),
            'old_values' => $this->redact($old),
            'new_values' => $this->redact($new),
            'ip_address' => $request?->ip(),
            'user_agent' => $request ? Str::limit((string) $request->userAgent(), 500, '') : null,
        ]);

        Log::channel(config('logging.default'))->info('audit.'.$action, [
            'actor_id' => $entry->user_id,
            'subject' => $entry->auditable_type ? $entry->auditable_type.'#'.$entry->auditable_id : null,
        ]);

        return $entry;
    }

    /**
     * @param  array<string, mixed>|null  $values
     * @return array<string, mixed>|null
     */
    private function redact(?array $values): ?array
    {
        if ($values === null) {
            return null;
        }

        foreach (self::REDACTED as $key) {
            if (array_key_exists($key, $values)) {
                $values[$key] = '[redacted]';
            }
        }

        return $values;
    }
}
