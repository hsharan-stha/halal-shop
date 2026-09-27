<?php

namespace App\Services\Catalog;

use App\Enums\HalalStatus;
use App\Enums\StorageType;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Published-catalog queries for the customer shop. Drafts, inactive brands
 * and categories, and unverified "certified" claims never reach these results.
 */
class StorefrontCatalog
{
    /**
     * @param  array{q?: ?string, category?: ?string, brand?: ?string, halal?: ?string, storage?: ?string, country?: ?string, availability?: ?string, min_price?: ?int, max_price?: ?int, sort?: string}  $filters
     * @param  list<int>|null  $categoryIds  overrides the category filter (a category page includes its children)
     */
    public function products(array $filters, ?array $categoryIds = null, ?int $brandId = null): LengthAwarePaginator
    {
        return $this->query($filters, $categoryIds, $brandId)
            ->paginate(config('shop.pagination.shop'))
            ->withQueryString();
    }

    /**
     * @param  list<int>  $ids
     * @return Collection<int, Product>
     */
    public function productsByIds(array $ids): Collection
    {
        if ($ids === []) {
            return collect();
        }

        return Product::query()->published()->withStorefront()->whereIn('products.id', $ids)->get()
            ->sortBy(fn (Product $product) => array_search($product->id, $ids, true))
            ->values();
    }

    /**
     * @return array{featured: Collection<int, Product>, arrivals: Collection<int, Product>, certified: Collection<int, Product>, frozen: Collection<int, Product>, categories: Collection<int, Category>, brands: Collection<int, Brand>}
     */
    public function home(): array
    {
        $take = fn (Builder $query) => $query->published()->withStorefront()->latest('published_at')->latest('products.id')->limit(8)->get();

        return [
            'featured' => $take(Product::query()->where('is_featured', true)),
            'arrivals' => $take(Product::query()),
            'certified' => $take(Product::query()->certifiedHalal()),
            'frozen' => $take(Product::query()->where('storage_type', StorageType::Frozen)),
            'categories' => $this->categoryTree(),
            'brands' => Brand::query()->active()->whereHas('products', fn (Builder $products) => $products->published())->orderBy('sort_order')->orderBy('name')->limit(12)->get(),
        ];
    }

    /**
     * @return array{products: LengthAwarePaginator<int, Product>, categories: Collection<int, Category>, brands: Collection<int, Brand>}
     */
    public function search(string $term, array $filters): array
    {
        $like = '%'.addcslashes($term, '%_\\').'%';

        return [
            'products' => $this->products($filters),
            'categories' => Category::query()->active()
                ->where(fn (Builder $query) => $query->where('name', 'like', $like)->orWhere('japanese_name', 'like', $like))
                ->ordered()->limit(6)->get(),
            'brands' => Brand::query()->active()
                ->where(fn (Builder $query) => $query->where('name', 'like', $like)->orWhere('japanese_name', 'like', $like))
                ->orderBy('name')->limit(6)->get(),
        ];
    }

    /**
     * @return array{categories: array<string, string>, brands: array<string, string>, countries: array<string, string>}
     */
    public function filterOptions(): array
    {
        $categories = [];

        foreach ($this->categoryTree() as $category) {
            $categories[$category->slug] = $category->localizedName();

            foreach ($category->children as $child) {
                $categories[$child->slug] = '— '.$child->localizedName();
            }
        }

        return [
            'categories' => $categories,
            'brands' => Brand::query()->active()->whereHas('products', fn (Builder $products) => $products->published())->orderBy('name')->get()
                ->mapWithKeys(fn (Brand $brand) => [$brand->slug => $brand->localizedName()])->all(),
            'countries' => Product::query()->published()->whereNotNull('country_of_origin')->distinct()->orderBy('country_of_origin')->pluck('country_of_origin')
                ->mapWithKeys(fn (string $code) => [$code => country_name($code)])->all(),
        ];
    }

    /**
     * Active top-level categories with their active children and a published-product count.
     *
     * @return Collection<int, Category>
     */
    public function categoryTree(): Collection
    {
        $categories = Category::query()->active()->ordered()->get();
        $counts = Product::query()->published()->selectRaw('category_id, count(*) as aggregate')->groupBy('category_id')->pluck('aggregate', 'category_id');
        $children = $categories->groupBy('parent_id');

        return $categories->whereNull('parent_id')->map(function (Category $category) use ($children, $counts) {
            $category->setRelation('children', $children->get($category->id, collect())->values());
            $ids = [$category->id, ...$category->children->pluck('id')];
            $category->setAttribute('products_count', (int) collect($ids)->sum(fn (int $id) => (int) ($counts[$id] ?? 0)));

            return $category;
        })->filter(fn (Category $category) => $category->products_count > 0)->values();
    }

    /**
     * @param  array{q?: ?string, category?: ?string, brand?: ?string, halal?: ?string, storage?: ?string, country?: ?string, availability?: ?string, min_price?: ?int, max_price?: ?int, sort?: string}  $filters
     * @param  list<int>|null  $categoryIds
     * @return Builder<Product>
     */
    private function query(array $filters, ?array $categoryIds, ?int $brandId): Builder
    {
        $query = Product::query()->published()->withStorefront();

        if ($categoryIds !== null) {
            $query->whereIn('category_id', $categoryIds);
        } elseif (filled($filters['category'] ?? null)) {
            $category = Category::query()->active()->where('slug', $filters['category'])->first();
            $query->whereIn('category_id', $category ? $category->descendantAndSelfIds() : []);
        }

        if ($brandId !== null) {
            $query->where('brand_id', $brandId);
        } elseif (filled($filters['brand'] ?? null)) {
            $query->whereHas('brand', fn (Builder $brands) => $brands->active()->where('slug', $filters['brand']));
        }

        if (filled($filters['q'] ?? null)) {
            $like = '%'.addcslashes($filters['q'], '%_\\').'%';
            $query->where(fn (Builder $inner) => $inner
                ->where('name', 'like', $like)
                ->orWhere('japanese_name', 'like', $like)
                ->orWhere('sku', 'like', $like)
                ->orWhereHas('variants', fn (Builder $variants) => $variants->where('sku', 'like', $like)->orWhere('barcode', 'like', $like))
                ->orWhereHas('brand', fn (Builder $brands) => $brands->where('name', 'like', $like)->orWhere('japanese_name', 'like', $like))
                ->orWhereHas('category', fn (Builder $categories) => $categories->where('name', 'like', $like)->orWhere('japanese_name', 'like', $like)));
        }

        if (filled($filters['halal'] ?? null)) {
            $status = HalalStatus::from($filters['halal']);

            if ($status === HalalStatus::Certified) {
                $query->certifiedHalal();
            } elseif ($status === HalalStatus::Unverified) {
                $query->where(fn (Builder $inner) => $inner
                    ->where('halal_status', HalalStatus::Unverified)
                    ->orWhere(fn (Builder $certified) => $certified->where('halal_status', HalalStatus::Certified)
                        ->whereDoesntHave('halalCertifications', fn (Builder $certificates) => $certificates->valid())));
            } else {
                $query->where('halal_status', $status);
            }
        }

        if (filled($filters['storage'] ?? null)) {
            $query->where('storage_type', $filters['storage']);
        }

        if (filled($filters['country'] ?? null)) {
            $query->where('country_of_origin', $filters['country']);
        }

        if (($filters['availability'] ?? null) === 'in_stock') {
            $query->inStock();
        } elseif (($filters['availability'] ?? null) === 'out_of_stock') {
            $query->outOfStock();
        }

        if (isset($filters['min_price']) || isset($filters['max_price'])) {
            $query->whereHas('variants', function (Builder $variants) use ($filters): void {
                $variants->where('is_active', true);

                if (isset($filters['min_price'])) {
                    $variants->where('price', '>=', $filters['min_price']);
                }

                if (isset($filters['max_price'])) {
                    $variants->where('price', '<=', $filters['max_price']);
                }
            });
        }

        $this->sort($query, $filters['sort'] ?? 'newest');

        return $query;
    }

    /**
     * @param  Builder<Product>  $query
     */
    private function sort(Builder $query, string $sort): void
    {
        $price = ProductVariant::query()->selectRaw('min(price)')->whereColumn('product_variants.product_id', 'products.id')->where('is_active', true);

        match ($sort) {
            'price_asc' => $query->orderBy($price)->orderBy('products.id'),
            'price_desc' => $query->orderByDesc($price)->orderByDesc('products.id'),
            'name' => $query->orderByRaw(app()->getLocale() === 'ja' ? 'coalesce(products.japanese_name, products.name)' : 'products.name'),
            default => $query->latest('published_at')->latest('products.id'),
        };
    }
}
