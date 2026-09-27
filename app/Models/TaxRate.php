<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['tax_class_id', 'rate_bps', 'effective_from', 'effective_to'])]
class TaxRate extends Model
{
    use Auditable;

    protected function casts(): array
    {
        return [
            'rate_bps' => 'integer',
            'effective_from' => 'date',
            'effective_to' => 'date',
        ];
    }

    /**
     * @return BelongsTo<TaxClass, $this>
     */
    public function taxClass(): BelongsTo
    {
        return $this->belongsTo(TaxClass::class);
    }

    public function percentage(): string
    {
        $percent = $this->rate_bps / 100;

        return (fmod($percent, 1.0) === 0.0 ? (string) (int) $percent : rtrim(rtrim(number_format($percent, 2), '0'), '.')).'%';
    }
}
