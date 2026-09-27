<?php

namespace App\Services\Catalog;

use App\Models\Product;
use Illuminate\Support\Collection;

/**
 * Products this browser has opened, newest first. Stored in the session
 * because it is a convenience, not an account record.
 */
class RecentlyViewed
{
    private const SESSION_KEY = 'recently_viewed';

    private const LIMIT = 12;

    public function remember(Product $product): void
    {
        $ids = array_values(array_filter($this->ids(), fn (int $id) => $id !== $product->id));
        array_unshift($ids, $product->id);
        session([self::SESSION_KEY => array_slice($ids, 0, self::LIMIT)]);
    }

    /**
     * @return Collection<int, Product>
     */
    public function products(?int $exceptId = null, int $limit = 8): Collection
    {
        $ids = array_values(array_filter($this->ids(), fn (int $id) => $id !== $exceptId));

        if ($ids === []) {
            return collect();
        }

        return Product::query()->published()->withStorefront()->whereIn('products.id', array_slice($ids, 0, $limit))->get()
            ->sortBy(fn (Product $product) => array_search($product->id, $ids, true))
            ->values();
    }

    /**
     * @return list<int>
     */
    private function ids(): array
    {
        return array_values(array_map('intval', (array) session(self::SESSION_KEY, [])));
    }
}
