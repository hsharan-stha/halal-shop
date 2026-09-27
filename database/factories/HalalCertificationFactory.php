<?php

namespace Database\Factories;

use App\Enums\CertificationStatus;
use App\Models\HalalCertification;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

/**
 * @extends Factory<HalalCertification>
 */
class HalalCertificationFactory extends Factory
{
    public function definition(): array
    {
        return [
            'certifying_body' => 'Sample Halal Council (fictional)',
            'certificate_number' => 'SHC-'.fake()->unique()->numerify('######'),
            'issued_at' => now()->subYear()->toDateString(),
            'expires_at' => now()->addYear()->toDateString(),
        ];
    }

    public function configure(): static
    {
        return $this->afterMaking(function (HalalCertification $certification): void {
            $certification->status ??= CertificationStatus::Pending;
        });
    }

    public function withFile(): static
    {
        return $this->afterMaking(function (HalalCertification $certification): void {
            $certification->forceFill([
                'file_path' => 'halal-certificates/'.fake()->lexify(str_repeat('?', 40)).'.pdf',
                'file_name' => 'certificate.pdf',
                'file_mime' => 'application/pdf',
            ]);
        });
    }

    public function verified(): static
    {
        return $this->withFile()->afterMaking(function (HalalCertification $certification): void {
            $certification->forceFill(['status' => CertificationStatus::Verified, 'verified_at' => now()]);
        });
    }

    public function rejected(): static
    {
        return $this->afterMaking(function (HalalCertification $certification): void {
            $certification->forceFill(['status' => CertificationStatus::Rejected, 'verified_at' => now(), 'rejection_reason' => 'Number does not match registry.']);
        });
    }

    public function expiresOn(Carbon|string $date): static
    {
        return $this->state(fn () => [
            'expires_at' => Carbon::parse($date)->toDateString(),
            'issued_at' => Carbon::parse($date)->subYears(2)->toDateString(),
        ]);
    }
}
