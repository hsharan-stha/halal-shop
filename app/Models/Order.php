<?php

namespace App\Models;

use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Order extends Model
{
    public function getRouteKeyName(): string
    {
        return 'order_number';
    }

    protected function casts(): array
    {
        return [
            'status' => OrderStatus::class,
            'payment_method' => PaymentMethod::class,
            'payment_status' => PaymentStatus::class,
            'items_total' => 'integer',
            'tax_total' => 'integer',
            'shipping_total' => 'integer',
            'cod_fee' => 'integer',
            'total' => 'integer',
            'placed_at' => 'datetime',
            'paid_at' => 'datetime',
            'shipped_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id')->withTrashed();
    }

    /**
     * @return HasMany<OrderItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function addressSummary(): string
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

    /**
     * Statuses a customer may cancel, from store settings.
     *
     * @return list<string>
     */
    public static function customerCancellable(): array
    {
        return array_values(array_filter(explode(',', (string) settings('orders.customer_cancellable_statuses', 'pending,confirmed'))));
    }

    public function customerMayCancel(): bool
    {
        return in_array($this->status->value, self::customerCancellable(), true) && $this->shipped_at === null;
    }
}
