<?php

namespace App\Exceptions\Inventory;

use RuntimeException;

/**
 * A stock operation that would break an inventory rule (insufficient or
 * expired stock, negative balances, invalid batch state). The message is a
 * translated, user-safe string.
 */
class InventoryException extends RuntimeException
{
    public static function insufficientStock(int $requested, int $available): self
    {
        return new self(__('admin.inventory.errors.insufficient', ['requested' => $requested, 'available' => $available]));
    }

    public static function negativeStock(): self
    {
        return new self(__('admin.inventory.errors.negative'));
    }

    public static function expiredOnReceipt(): self
    {
        return new self(__('admin.inventory.errors.expired_on_receipt'));
    }

    public static function batchNotAvailable(): self
    {
        return new self(__('admin.inventory.errors.batch_not_available'));
    }

    public static function batchRequired(): self
    {
        return new self(__('admin.inventory.errors.batch_required'));
    }

    public static function invalidQuantity(): self
    {
        return new self(__('admin.inventory.errors.invalid_quantity'));
    }

    public static function cannotReleaseExpired(): self
    {
        return new self(__('admin.inventory.errors.cannot_release_expired'));
    }

    public static function trackingLocked(): self
    {
        return new self(__('admin.inventory.errors.tracking_locked'));
    }
}
