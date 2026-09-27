<?php

namespace App\Http\Controllers\Shop;

use App\Http\Controllers\Controller;
use App\Http\Requests\Shop\CatalogFilterRequest;
use App\Models\Category;
use App\Services\Catalog\StorefrontCatalog;
use App\Services\Catalog\WishlistService;
use Illuminate\View\View;

class CategoryController extends Controller
{
    public function index(StorefrontCatalog $catalog): View
    {
        return view('shop.categories.index', [
            'categories' => $catalog->categoryTree(),
        ]);
    }

    public function show(CatalogFilterRequest $request, Category $category, StorefrontCatalog $catalog, WishlistService $wishlist): View
    {
        abort_unless($category->is_active, 404);

        $filters = $request->filters();
        $category->load(['parent', 'children' => fn ($query) => $query->active()->ordered()]);

        return view('shop.catalog.index', [
            'title' => $category->localizedName(),
            'description' => $category->translate('description'),
            'products' => $catalog->products($filters, $category->descendantAndSelfIds()),
            'filters' => $filters,
            'options' => $catalog->filterOptions(),
            'wishlistIds' => $wishlist->ids(),
            'action' => route('categories.show', $category),
            'locked' => ['category'],
            'category' => $category,
        ]);
    }
}
