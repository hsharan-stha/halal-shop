<?php

namespace App\Exceptions\Checkout;

use RuntimeException;

/**
 * A cart or checkout step that cannot be completed. The message is translated
 * and safe to show to the customer.
 */
class CheckoutException extends RuntimeException
{
    public static function unavailable(): self
    {
        return new self(__('shop.checkout.errors.unavailable'));
    }

    public static function insufficient(int $available): self
    {
        return new self(__('shop.checkout.errors.insufficient', ['available' => $available]));
    }

    public static function empty(): self
    {
        return new self(__('shop.checkout.errors.empty'));
    }

    public static function paymentUnavailable(): self
    {
        return new self(__('shop.checkout.errors.payment'));
    }

    public static function notCancellable(): self
    {
        return new self(__('shop.checkout.errors.not_cancellable'));
    }

    public static function invalidState(): self
    {
        return new self(__('shop.checkout.errors.invalid_state'));
    }
}
