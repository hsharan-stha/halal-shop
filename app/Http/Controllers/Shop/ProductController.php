<?php

namespace App\Http\Controllers\Shop;

use App\Http\Controllers\Controller;
use App\Http\Requests\Shop\CatalogFilterRequest;
use App\Models\Product;
use App\Services\Catalog\RecentlyViewed;
use App\Services\Catalog\StorefrontCatalog;
use App\Services\Catalog\WishlistService;
use Illuminate\View\View;

class ProductController extends Controller
{
    public function index(CatalogFilterRequest $request, StorefrontCatalog $catalog, WishlistService $wishlist): View
    {
        $filters = $request->filters();

        return view('shop.catalog.index', [
            'title' => __('shop.catalog.title'),
            'description' => __('shop.catalog.description'),
            'products' => $catalog->products($filters),
            'filters' => $filters,
            'options' => $catalog->filterOptions(),
            'wishlistIds' => $wishlist->ids(),
            'action' => route('shop.index'),
            'locked' => [],
        ]);
    }

    public function show(string $product, RecentlyViewed $recentlyViewed, WishlistService $wishlist, StorefrontCatalog $catalog): View
    {
        $product = Product::query()->published()
            ->with([
                'brand',
                'category.parent',
                'images',
                'variants' => fn ($query) => $query->where('is_active', true)->with(['inventoryItem' => fn ($items) => $items->withSellableQuantity()]),
                'halalCertifications' => fn ($query) => $query->valid()->orderBy('expires_at'),
            ])
            ->where('slug', $product)
            ->firstOrFail();

        $recentlyViewed->remember($product);

        $selected = $product->variants->firstWhere('id', (int) request('variant')) ?? $product->variants->firstWhere('is_default', true) ?? $product->variants->first();

        return view('shop.products.show', [
            'product' => $product,
            'selected' => $selected,
            'related' => $product->category_id
                ? $catalog->productsByIds(
                    Product::query()->published()->where('category_id', $product->category_id)->whereKeyNot($product->id)->latest('published_at')->limit(4)->pluck('id')->all(),
                )
                : collect(),
            'recent' => $recentlyViewed->products($product->id),
            'wishlistIds' => $wishlist->ids(),
        ]);
    }
}
