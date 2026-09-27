<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['label', 'recipient_name', 'phone', 'postal_code', 'prefecture', 'city', 'ward', 'town', 'street', 'building', 'room', 'is_default'])]
class Address extends Model
{
    protected function casts(): array
    {
        return [
            'is_default' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * One line, suitable for an order list.
     */
    public function summary(): string
    {
        return collect([
            $this->postal_code,
            $this->prefecture,
            $this->city,
            $this->ward,
            $this->town,
            $this->street,
            $this->building,
            $this->room,
        ])->filter()->implode(' ');
    }
}
