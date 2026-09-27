<?php

namespace App\Models\Concerns;

use App\Services\AuditLogger;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * Writes created/updated/deleted audit entries for staff-managed models.
 *
 * Models may define `protected array $auditExclude` to skip noisy columns.
 */
trait Auditable
{
    public static function bootAuditable(): void
    {
        static::created(function (Model $model): void {
            $model->writeAudit('created', null, $model->auditableAttributes($model->getAttributes()));
        });

        static::updated(function (Model $model): void {
            $changes = $model->auditableAttributes($model->getChanges());

            if ($changes === []) {
                return;
            }

            $original = array_intersect_key($model->getOriginal(), $changes);
            $model->writeAudit('updated', $original, $changes);
        });

        static::deleted(function (Model $model): void {
            $model->writeAudit('deleted', null, null);
        });
    }

    public function auditPrefix(): string
    {
        return Str::snake(class_basename($this));
    }

    /**
     * @param  array<string, mixed>|null  $old
     * @param  array<string, mixed>|null  $new
     */
    protected function writeAudit(string $event, ?array $old, ?array $new): void
    {
        if (! auth()->check()) {
            return;
        }

        app(AuditLogger::class)->log($this->auditPrefix().'.'.$event, $this, $old, $new);
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    protected function auditableAttributes(array $attributes): array
    {
        $exclude = array_merge(['updated_at', 'created_at'], property_exists($this, 'auditExclude') ? $this->auditExclude : []);

        return array_diff_key($attributes, array_flip($exclude));
    }
}
