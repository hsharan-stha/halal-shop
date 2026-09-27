<?php

namespace App\Http\Controllers\Shop;

use App\Http\Controllers\Controller;
use App\Http\Requests\Shop\CatalogFilterRequest;
use App\Services\Catalog\StorefrontCatalog;
use App\Services\Catalog\WishlistService;
use Illuminate\View\View;

class SearchController extends Controller
{
    public function __invoke(CatalogFilterRequest $request, StorefrontCatalog $catalog, WishlistService $wishlist): View
    {
        $filters = $request->filters();
        $term = (string) ($filters['q'] ?? '');

        return view('shop.search', [
            'filters' => $filters,
            'options' => $catalog->filterOptions(),
            'wishlistIds' => $wishlist->ids(),
            'term' => $term,
            ...($term === ''
                ? ['products' => null, 'categories' => collect(), 'brands' => collect()]
                : $catalog->search($term, $filters)),
        ]);
    }
}
