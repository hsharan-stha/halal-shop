<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\CategoryRequest;
use App\Models\Category;
use App\Services\Media\ImageStorage;
use App\Support\ShopAccess;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Collection;
use Illuminate\View\View;

class CategoryController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('can:categories.view', only: ['index']),
            new Middleware('can:categories.manage', except: ['index']),
        ];
    }

    public function index(Request $request): View
    {
        $search = trim((string) $request->string('q'));

        $query = Category::query()->withCount(['products', 'children'])->with(['parent:id,name,japanese_name', 'shop:id,name'])->ordered();

        if ($search !== '') {
            $categories = $query
                ->where(fn ($inner) => $inner
                    ->where('name', 'like', '%'.$search.'%')
                    ->orWhere('japanese_name', 'like', '%'.$search.'%')
                    ->orWhere('slug', 'like', '%'.$search.'%'))
                ->paginate(config('shop.pagination.admin'))
                ->withQueryString();

            return view('admin.categories.index', ['rows' => $categories->map(fn (Category $category) => ['category' => $category, 'depth' => 0]), 'paginator' => $categories, 'search' => $search]);
        }

        return view('admin.categories.index', ['rows' => $this->flattenTree($query->get()), 'paginator' => null, 'search' => $search]);
    }

    /**
     * @param  Collection<int, Category>  $categories
     * @return Collection<int, array{category: Category, depth: int}>
     */
    private function flattenTree($categories): Collection
    {
        $byParent = $categories->groupBy(fn (Category $category) => $category->parent_id ?? 0);
        $rows = collect();

        $walk = function (int $parentId, int $depth) use (&$walk, $byParent, $rows): void {
            foreach ($byParent->get($parentId, collect()) as $category) {
                $rows->push(['category' => $category, 'depth' => $depth]);
                $walk($category->id, $depth + 1);
            }
        };

        $walk(0, 0);

        return $rows;
    }

    public function create(Request $request): View
    {
        $category = new Category(['is_active' => true, 'parent_id' => $request->integer('parent') ?: null]);

        return view('admin.categories.form', ['category' => $category, 'parents' => Category::treeOptions()]);
    }

    public function store(CategoryRequest $request, ImageStorage $images): RedirectResponse
    {
        $category = new Category($request->attributesForModel());
        $category->shop_id = ShopAccess::$tenantId;

        if ($request->hasFile('image')) {
            $category->image_path = $images->store($request->file('image'), 'categories', 1200)['path'];
        }

        $category->save();

        return redirect()->route('admin.categories.index')->with('success', __('admin.categories.created'));
    }

    public function edit(Category $category): View
    {
        $this->authorizeShopOwnership($category->shop_id);

        return view('admin.categories.form', [
            'category' => $category,
            'parents' => Category::treeOptions($category->descendantAndSelfIds()),
        ]);
    }

    public function update(CategoryRequest $request, Category $category, ImageStorage $images): RedirectResponse
    {
        $this->authorizeShopOwnership($category->shop_id);

        $category->fill($request->attributesForModel());
        $previous = $category->image_path;

        if ($request->hasFile('image')) {
            $category->image_path = $images->store($request->file('image'), 'categories', 1200)['path'];
        } elseif ($request->boolean('image_remove')) {
            $category->image_path = null;
        }

        $category->save();

        if ($previous && $previous !== $category->image_path) {
            $images->delete($previous);
        }

        return redirect()->route('admin.categories.index')->with('success', __('admin.categories.updated'));
    }

    public function destroy(Category $category, ImageStorage $images): RedirectResponse
    {
        $this->authorizeShopOwnership($category->shop_id);

        if ($category->products()->withTrashed()->exists()) {
            return back()->with('error', __('admin.categories.has_products'));
        }

        $category->children()->update(['parent_id' => $category->parent_id]);
        $category->delete();
        $images->delete($category->image_path);

        return redirect()->route('admin.categories.index')->with('success', __('admin.categories.deleted'));
    }
}
