<?php

namespace App\Http\Controllers\Admin;

use App\Enums\CertificationStatus;
use App\Enums\HalalStatus;
use App\Enums\ProductStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ProductRequest;
use App\Models\Brand;
use App\Models\Category;
use App\Models\HalalCertification;
use App\Models\Product;
use App\Models\Supplier;
use App\Models\TaxClass;
use App\Services\Catalog\ProductService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ProductController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('can:products.view', only: ['index']),
            new Middleware('can:products.create', only: ['create', 'store', 'duplicate']),
            new Middleware('can:products.update', only: ['edit', 'update']),
            new Middleware('can:products.delete', only: ['destroy', 'restore']),
        ];
    }

    public function index(Request $request): View
    {
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', Rule::enum(ProductStatus::class)],
            'halal' => ['nullable', Rule::enum(HalalStatus::class)],
            'category' => ['nullable', 'integer'],
            'brand' => ['nullable', 'integer'],
            'label' => ['nullable', 'in:reviewed,unreviewed'],
            'trashed' => ['nullable', 'boolean'],
        ]);

        $products = Product::query()
            ->with(['category:id,name,japanese_name', 'brand:id,name,japanese_name', 'images', 'variants', 'halalCertifications'])
            ->when($filters['q'] ?? null, fn ($query, string $q) => $query->where(fn ($inner) => $inner
                ->where('name', 'like', '%'.$q.'%')
                ->orWhere('japanese_name', 'like', '%'.$q.'%')
                ->orWhere('sku', 'like', '%'.$q.'%')
                ->orWhereHas('variants', fn ($variants) => $variants->where('sku', 'like', '%'.$q.'%')->orWhere('barcode', $q))))
            ->when($filters['status'] ?? null, fn ($query, string $status) => $query->where('status', $status))
            ->when($filters['halal'] ?? null, fn ($query, string $halal) => $query->where('halal_status', $halal))
            ->when($filters['category'] ?? null, function ($query, int $categoryId) {
                $category = Category::query()->find($categoryId);
                $query->whereIn('category_id', $category ? $category->descendantAndSelfIds() : [0]);
            })
            ->when($filters['brand'] ?? null, fn ($query, int $brand) => $query->where('brand_id', $brand))
            ->when(($filters['label'] ?? null) === 'reviewed', fn ($query) => $query->whereNotNull('food_label_reviewed_at'))
            ->when(($filters['label'] ?? null) === 'unreviewed', fn ($query) => $query->whereNull('food_label_reviewed_at'))
            ->when($filters['trashed'] ?? false, fn ($query) => $query->onlyTrashed())
            ->latest('updated_at')
            ->paginate(config('shop.pagination.admin'))
            ->withQueryString();

        return view('admin.products.index', [
            'products' => $products,
            'filters' => $filters,
            'categories' => Category::treeOptions(),
            'brands' => Brand::query()->orderBy('name')->get()->mapWithKeys(fn (Brand $brand) => [$brand->id => $brand->localizedName()])->all(),
        ]);
    }

    public function create(): View
    {
        $product = new Product([
            'status' => ProductStatus::Draft,
            'halal_status' => HalalStatus::Unverified,
            'min_order_quantity' => 1,
            'tax_class_id' => TaxClass::default()?->id,
        ]);

        return view('admin.products.form', $this->formData($product));
    }

    public function store(ProductRequest $request, ProductService $service): RedirectResponse
    {
        $attributes = $request->productAttributes();
        $attributes['slug'] ??= $service->uniqueSlug($attributes['name']);

        $product = $service->create($attributes, $request->variantAttributes(), $request->certificationIds(), $request->user(), $request->boolean('food_label_reviewed'));

        foreach ($request->file('images', []) as $file) {
            $service->addImage($product, $file);
        }

        return redirect()->route('admin.products.edit', $product)->with('success', __('admin.products.created'));
    }

    public function edit(Product $product): View
    {
        $product->load(['halalCertifications', 'foodLabelReviewer', 'variants', 'images']);

        return view('admin.products.form', $this->formData($product));
    }

    public function update(ProductRequest $request, Product $product, ProductService $service): RedirectResponse
    {
        $attributes = $request->productAttributes();
        $attributes['slug'] ??= $service->uniqueSlug($attributes['name'], $product->id);

        $service->update($product, $attributes, $request->certificationIds(), $request->user(), $request->boolean('food_label_reviewed'));

        return redirect()->route('admin.products.edit', $product)->with('success', __('admin.products.updated'));
    }

    public function duplicate(Product $product, ProductService $service): RedirectResponse
    {
        $copy = $service->duplicate($product);

        return redirect()->route('admin.products.edit', $copy)->with('success', __('admin.products.duplicated'));
    }

    public function destroy(Product $product): RedirectResponse
    {
        $product->delete();

        return redirect()->route('admin.products.index')->with('success', __('admin.products.deleted'));
    }

    public function restore(int $product): RedirectResponse
    {
        $model = Product::onlyTrashed()->findOrFail($product);
        $model->restore();

        return redirect()->route('admin.products.edit', $model)->with('success', __('admin.products.restored'));
    }

    public function bulk(Request $request, ProductService $service): RedirectResponse
    {
        $data = $request->validate([
            'action' => ['required', 'in:activate,draft,archive,delete'],
            'ids' => ['required', 'array', 'min:1', 'max:200'],
            'ids.*' => ['integer', 'distinct'],
        ]);

        abort_unless($request->user()->can($data['action'] === 'delete' ? 'products.delete' : 'products.update'), 403);

        $products = Product::query()->whereKey($data['ids'])->get();

        $count = match ($data['action']) {
            'activate' => $service->bulkStatus($products, ProductStatus::Active),
            'draft' => $service->bulkStatus($products, ProductStatus::Draft),
            'archive' => $service->bulkStatus($products, ProductStatus::Archived),
            'delete' => $service->bulkDelete($products),
        };

        return back()->with('success', trans_choice('admin.products.bulk_done', $count, ['count' => $count]));
    }

    /**
     * @return array<string, mixed>
     */
    private function formData(Product $product): array
    {
        $certifications = HalalCertification::query()
            ->with('brand:id,name')
            ->where(fn ($query) => $query
                ->where('status', '!=', CertificationStatus::Rejected)
                ->orWhereIn('id', $product->exists ? $product->halalCertifications->pluck('id') : []))
            ->orderBy('certifying_body')
            ->get();

        return [
            'product' => $product,
            'categories' => Category::treeOptions(),
            'brands' => Brand::query()->orderBy('name')->get()->mapWithKeys(fn (Brand $brand) => [$brand->id => $brand->localizedName()])->all(),
            'suppliers' => Supplier::query()->orderBy('name')->pluck('name', 'id')->all(),
            'taxClasses' => TaxClass::query()->with('rates')->orderBy('id')->get()->mapWithKeys(fn (TaxClass $class) => [$class->id => $class->label()])->all(),
            'certifications' => $certifications,
        ];
    }
}
