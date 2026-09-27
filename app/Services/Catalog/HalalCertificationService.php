<?php

namespace App\Services\Catalog;

use App\Enums\CertificationStatus;
use App\Models\HalalCertification;
use App\Models\User;
use App\Notifications\HalalCertificateExpiring;
use App\Services\AuditLogger;
use App\Support\ShopAccess;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;

class HalalCertificationService
{
    private const DIRECTORY = 'halal-certificates';

    public function __construct(private readonly AuditLogger $auditLogger) {}

    /**
     * Create or update a certificate. Any change to the certificate data sends
     * it back to Pending so it must be verified again before it counts.
     *
     * @param  array<string, mixed>  $data
     * @param  list<int>  $productIds
     */
    public function save(HalalCertification $certification, array $data, array $productIds, ?UploadedFile $file = null): HalalCertification
    {
        return DB::transaction(function () use ($certification, $data, $productIds, $file): HalalCertification {
            $certification->fill($data);
            $materialChange = ! $certification->exists
                || $file !== null
                || $certification->isDirty(['certifying_body', 'certificate_number', 'scope', 'issued_at', 'expires_at', 'brand_id']);

            if ($certification->isDirty('expires_at')) {
                $certification->last_alert_days = null;
            }

            if ($file) {
                $previous = $certification->file_path;
                $certification->forceFill([
                    'file_path' => $file->storeAs(self::DIRECTORY, Str::random(40).'.'.$this->extensionFor($file), config('shop.private_disk')),
                    'file_name' => Str::limit(basename($file->getClientOriginalName()), 180, ''),
                    'file_mime' => $file->getMimeType(),
                ]);

                if ($previous) {
                    DB::afterCommit(fn () => $this->disk()->delete($previous));
                }
            }

            if ($materialChange) {
                $certification->forceFill([
                    'status' => CertificationStatus::Pending,
                    'verified_by' => null,
                    'verified_at' => null,
                    'rejection_reason' => null,
                ]);
            }

            $certification->save();
            $certification->products()->sync($productIds);

            return $certification;
        });
    }

    public function verify(HalalCertification $certification, User $actor): void
    {
        $certification->forceFill([
            'status' => CertificationStatus::Verified,
            'verified_by' => $actor->id,
            'verified_at' => now(),
            'rejection_reason' => null,
        ])->save();

        $this->auditLogger->log('halal_certification.verified', $certification);
    }

    public function reject(HalalCertification $certification, User $actor, string $reason): void
    {
        $certification->forceFill([
            'status' => CertificationStatus::Rejected,
            'verified_by' => $actor->id,
            'verified_at' => now(),
            'rejection_reason' => $reason,
        ])->save();

        $this->auditLogger->log('halal_certification.rejected', $certification, null, ['reason' => $reason]);
    }

    public function delete(HalalCertification $certification): void
    {
        $certification->products()->detach();
        $certification->delete();
    }

    public function fileUrl(HalalCertification $certification): ?string
    {
        if (! $certification->hasFile()) {
            return null;
        }

        return URL::temporarySignedRoute(
            'admin.halal-certifications.file',
            now()->addMinutes((int) config('shop.private_url_ttl')),
            $certification,
        );
    }

    public function disk(): Filesystem
    {
        return Storage::disk(config('shop.private_disk'));
    }

    /**
     * Notify staff when a verified certificate crosses a warning threshold or
     * expires. Each threshold is sent once per certificate.
     *
     * @return int number of certificates alerted
     */
    public function sendExpiryAlerts(): int
    {
        if (! settings('notifications.expiry_alerts')) {
            return 0;
        }

        $thresholds = collect(settings('halal.certificate_expiry_warning_days'))->map(fn ($days) => (int) $days)->filter(fn (int $days) => $days > 0)->sort()->values();
        $horizon = (int) ($thresholds->max() ?? 0);
        $recipients = $this->recipients();
        $alertEmail = settings('notifications.admin_alert_email');
        $sent = 0;

        HalalCertification::query()
            ->where('status', CertificationStatus::Verified)
            ->whereDate('expires_at', '<=', HalalCertification::today()->addDays($horizon))
            ->with('products:id,name,japanese_name')
            ->chunkById(100, function (Collection $certifications) use ($thresholds, $recipients, $alertEmail, &$sent): void {
                foreach ($certifications as $certification) {
                    $days = $certification->daysUntilExpiry();
                    $threshold = $days < 0 ? 0 : $thresholds->first(fn (int $limit) => $days <= $limit);

                    if ($threshold === null || ($certification->last_alert_days !== null && $certification->last_alert_days <= $threshold)) {
                        continue;
                    }

                    $notification = new HalalCertificateExpiring($certification, $days);
                    Notification::send($this->recipientsFor($certification, $recipients), $notification);

                    if (filled($alertEmail)) {
                        Notification::route('mail', $alertEmail)->notify($notification);
                    }

                    $certification->forceFill(['last_alert_days' => $threshold])->saveQuietly();
                    $sent++;
                }
            });

        return $sent;
    }

    /**
     * @return Collection<int, User>
     */
    private function recipients(): Collection
    {
        return User::query()->staff()->where('status', 'active')->with('roles.permissions')->get()
            ->filter(fn (User $user) => $user->hasPermission('halal_certificates.view'))
            ->values();
    }

    /**
     * A halal shop is only told about shared certificates and its own.
     *
     * @param  Collection<int, User>  $staff
     * @return Collection<int, User>
     */
    private function recipientsFor(HalalCertification $certification, Collection $staff): Collection
    {
        return $staff
            ->filter(function (User $user) use ($certification): bool {
                $shopId = ShopAccess::id($user);

                return $shopId === null || $certification->shop_id === null || $shopId === (int) $certification->shop_id;
            })
            ->values();
    }

    private function extensionFor(UploadedFile $file): string
    {
        return match ($file->getMimeType()) {
            'application/pdf' => 'pdf',
            'image/png' => 'png',
            'image/webp' => 'webp',
            default => 'jpg',
        };
    }
}
