<?php

namespace App\Enums;

use App\Enums\Concerns\HasLabel;

enum CertificationStatus: string
{
    use HasLabel;

    case Pending = 'pending';
    case Verified = 'verified';
    case Rejected = 'rejected';

    public function color(): string
    {
        return match ($this) {
            self::Pending => 'warning',
            self::Verified => 'success',
            self::Rejected => 'danger',
        };
    }
}
