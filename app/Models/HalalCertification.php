<?php

namespace App\Models;

use App\Enums\CertificationStatus;
use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

#[Fillable(['brand_id', 'certifying_body', 'certificate_number', 'scope', 'issued_at', 'expires_at', 'notes'])]
class HalalCertification extends Model
{
    use Auditable, HasFactory, SoftDeletes;

    /**
     * @var list<string>
     */
    protected array $auditExclude = ['last_alert_days'];

    protected function casts(): array
    {
        return [
            'issued_at' => 'date',
            'expires_at' => 'date',
            'verified_at' => 'datetime',
            'status' => CertificationStatus::class,
            'last_alert_days' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Brand, $this>
     */
    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class)->withTrashed();
    }

    /**
     * @return BelongsToMany<Product, $this>
     */
    public function products(): BelongsToMany
    {
        return $this->belongsToMany(Product::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function verifier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by')->withTrashed();
    }

    /**
     * Verified by staff and not yet expired (Japan date).
     *
     * @param  Builder<HalalCertification>  $query
     */
    public function scopeValid(Builder $query): void
    {
        $query->where('status', CertificationStatus::Verified)
            ->whereDate('expires_at', '>=', self::today());
    }

    /**
     * @param  Builder<HalalCertification>  $query
     */
    public function scopeExpiringWithin(Builder $query, int $days): void
    {
        $query->where('status', CertificationStatus::Verified)
            ->whereDate('expires_at', '>=', self::today())
            ->whereDate('expires_at', '<=', self::today()->addDays($days));
    }

    public function isValid(): bool
    {
        return $this->status === CertificationStatus::Verified && ! $this->isExpired();
    }

    public function isExpired(): bool
    {
        return $this->expires_at->toDateString() < self::today()->toDateString();
    }

    public function daysUntilExpiry(): int
    {
        $expiry = Carbon::parse($this->expires_at->toDateString(), config('app.display_timezone'));

        return (int) round(self::today()->diffInDays($expiry, false));
    }

    public function hasFile(): bool
    {
        return filled($this->file_path);
    }

    public static function today(): Carbon
    {
        return Carbon::now(config('app.display_timezone'))->startOfDay();
    }
}
