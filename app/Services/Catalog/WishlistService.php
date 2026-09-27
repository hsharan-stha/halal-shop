<?php

namespace App\Services\Catalog;

use App\Models\Product;
use App\Models\User;
use Illuminate\Support\Facades\Auth;

/**
 * Wishlist for the signed-in customer, or a session list for guests.
 * A guest list is copied onto the account at login and then cleared.
 */
class WishlistService
{
    private const SESSION_KEY = 'wishlist';

    /**
     * @return list<int>
     */
    public function ids(): array
    {
        $user = Auth::user();

        if ($user instanceof User) {
            return $user->wishlistItems()->latest('id')->pluck('product_id')->map(fn ($id) => (int) $id)->all();
        }

        return array_values(array_map('intval', (array) session(self::SESSION_KEY, [])));
    }

    public function contains(Product $product): bool
    {
        return in_array($product->id, $this->ids(), true);
    }

    public function add(Product $product): void
    {
        $user = Auth::user();

        if ($user instanceof User) {
            $user->wishlistItems()->firstOrCreate(['product_id' => $product->id]);

            return;
        }

        $ids = $this->ids();

        if (! in_array($product->id, $ids, true)) {
            array_unshift($ids, $product->id);
            session([self::SESSION_KEY => $ids]);
        }
    }

    public function remove(Product $product): void
    {
        $user = Auth::user();

        if ($user instanceof User) {
            $user->wishlistItems()->where('product_id', $product->id)->delete();

            return;
        }

        session([self::SESSION_KEY => array_values(array_filter($this->ids(), fn (int $id) => $id !== $product->id))]);
    }

    /**
     * Copy a guest wishlist onto the account. Existing items are kept once.
     */
    public function mergeSessionInto(User $user): void
    {
        $ids = array_map('intval', (array) session()->pull(self::SESSION_KEY, []));

        if ($ids === []) {
            return;
        }

        $published = Product::query()->published()->whereIn('id', $ids)->pluck('id');

        foreach ($published as $productId) {
            $user->wishlistItems()->firstOrCreate(['product_id' => $productId]);
        }
    }
}
