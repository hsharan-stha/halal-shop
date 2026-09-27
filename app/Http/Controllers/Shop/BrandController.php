<?php

namespace App\Http\Controllers\Shop;

use App\Http\Controllers\Controller;
use App\Http\Requests\Shop\CatalogFilterRequest;
use App\Models\Brand;
use App\Services\Catalog\StorefrontCatalog;
use App\Services\Catalog\WishlistService;
use Illuminate\View\View;

class BrandController extends Controller
{
    public function show(CatalogFilterRequest $request, Brand $brand, StorefrontCatalog $catalog, WishlistService $wishlist): View
    {
        abort_unless($brand->is_active, 404);

        $filters = $request->filters();

        return view('shop.catalog.index', [
            'title' => $brand->localizedName(),
            'description' => $brand->translate('description'),
            'products' => $catalog->products($filters, brandId: $brand->id),
            'filters' => $filters,
            'options' => $catalog->filterOptions(),
            'wishlistIds' => $wishlist->ids(),
            'action' => route('brands.show', $brand),
            'locked' => ['brand'],
            'brand' => $brand,
        ]);
    }
}
