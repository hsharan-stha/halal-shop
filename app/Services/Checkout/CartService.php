<?php

namespace App\Services\Checkout;

use App\Exceptions\Checkout\CheckoutException;
use App\Models\Cart;
use App\Models\ProductVariant;
use App\Models\User;
use Illuminate\Support\Facades\Auth;

/**
 * The customer's basket. Guests keep it in the session; a signed-in customer
 * keeps it on their account. Quantities are limited by the product rules and
 * by stock that can actually be sold.
 */
class CartService
{
    private const SESSION_KEY = 'cart';

    public function count(): int
    {
        return (int) array_sum($this->quantities());
    }

    /**
     * @return array<int, int>
     */
    public function quantities(): array
    {
        $user = Auth::user();

        if ($user instanceof User) {
            $cart = $user->cart;

            if (! $cart instanceof Cart) {
                return [];
            }

            return $cart->items()->pluck('quantity', 'product_variant_id')
                ->map(fn ($quantity) => (int) $quantity)
                ->all();
        }

        $stored = [];

        foreach ((array) session(self::SESSION_KEY, []) as $id => $quantity) {
            $stored[(int) $id] = (int) $quantity;
        }

        return $stored;
    }

    public function add(ProductVariant $variant, int $quantity): void
    {
        $current = $this->quantities()[$variant->id] ?? 0;
        $this->set($variant, $current + $quantity);
    }

    public function set(ProductVariant $variant, int $quantity): void
    {
        $variant->loadMissing(['product', 'inventoryItem' => fn ($query) => $query->withSellableQuantity()]);
        $this->assertSellable($variant);

        $quantities = $this->quantities();

        if ($quantity < 1) {
            unset($quantities[$variant->id]);
            $this->persist($quantities);

            return;
        }

        $quantities[$variant->id] = $this->clamp($variant, $quantity);
        $this->persist($quantities);
    }

    public function remove(ProductVariant $variant): void
    {
        $quantities = $this->quantities();
        unset($quantities[$variant->id]);
        $this->persist($quantities);
    }

    public function clear(): void
    {
        $this->persist([]);
    }

    /**
     * Copy a guest basket onto the account. Lines that can no longer be sold
     * are left out.
     */
    public function mergeSessionInto(User $user): void
    {
        $guest = [];

        foreach ((array) session()->pull(self::SESSION_KEY, []) as $variantId => $quantity) {
            $guest[(int) $variantId] = (int) $quantity;
        }

        if ($guest === [] || ! $user->is(Auth::user())) {
            return;
        }

        foreach ($guest as $variantId => $quantity) {
            $variant = ProductVariant::query()->find((int) $variantId);

            if (! $variant instanceof ProductVariant) {
                continue;
            }

            try {
                $this->add($variant, (int) $quantity);
            } catch (CheckoutException) {
                continue;
            }
        }
    }

    private function assertSellable(ProductVariant $variant): void
    {
        $product = $variant->product;

        if (! $variant->is_active || $product === null || ! $product->isPublished()) {
            throw CheckoutException::unavailable();
        }

        $sellable = $variant->inventoryItem?->sellableQuantity() ?? 0;
        $minimum = max(1, (int) $product->min_order_quantity);

        if ($sellable < $minimum) {
            throw CheckoutException::insufficient($sellable);
        }
    }

    private function clamp(ProductVariant $variant, int $quantity): int
    {
        $product = $variant->product;
        $minimum = max(1, (int) $product->min_order_quantity);
        $sellable = $variant->inventoryItem?->sellableQuantity() ?? 0;
        $maximum = $product->max_order_quantity ? min((int) $product->max_order_quantity, $sellable) : $sellable;

        if ($quantity > $maximum) {
            throw CheckoutException::insufficient($maximum);
        }

        return max($minimum, $quantity);
    }

    /**
     * @param  array<int, int>  $quantities
     */
    private function persist(array $quantities): void
    {
        $user = Auth::user();

        if ($user instanceof User) {
            $cart = $user->cart()->firstOrCreate([]);
            $cart->items()->delete();

            foreach ($quantities as $variantId => $quantity) {
                if ($quantity > 0) {
                    $cart->items()->create([
                        'product_variant_id' => $variantId,
                        'quantity' => $quantity,
                    ]);
                }
            }

            $user->unsetRelation('cart');

            return;
        }

        session([self::SESSION_KEY => $quantities]);
    }
}
