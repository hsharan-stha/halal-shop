<?php

namespace App\Http\Requests\Admin;

use App\Enums\Allergen;
use App\Enums\HalalStatus;
use App\Enums\ProductStatus;
use App\Enums\StorageType;
use App\Http\Requests\Concerns\HandlesTranslations;
use App\Models\Product;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class ProductRequest extends FormRequest
{
    use HandlesTranslations;

    public const MAX_PRICE = 10_000_000;

    public function authorize(): bool
    {
        return $this->user()->can($this->route('product') ? 'products.update' : 'products.create');
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'sku' => Str::upper(trim((string) $this->input('sku'))),
            'slug' => $this->filled('slug') ? Str::slug((string) $this->input('slug')) : null,
            'country_of_origin' => $this->filled('country_of_origin') ? Str::upper((string) $this->input('country_of_origin')) : null,
        ]);

        if (is_array($this->input('variant'))) {
            $this->merge(['variant' => [...$this->input('variant'), 'sku' => Str::upper(trim((string) $this->input('variant.sku') ?: (string) $this->input('sku')))]]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        /** @var Product|null $product */
        $product = $this->route('product');
        $maxDimension = config('shop.uploads.image_max_dimension');

        $rules = [
            'category_id' => ['required', 'integer', Rule::exists('categories', 'id')],
            'brand_id' => ['nullable', 'integer', Rule::exists('brands', 'id')->whereNull('deleted_at')],
            'supplier_id' => ['nullable', 'integer', Rule::exists('suppliers', 'id')->whereNull('deleted_at')],
            'tax_class_id' => ['required', 'integer', Rule::exists('tax_classes', 'id')],
            'sku' => ['required', 'string', 'max:64', 'regex:/^[A-Z0-9][A-Z0-9._-]*$/', Rule::unique('products', 'sku')->ignore($product?->id)],
            'slug' => ['nullable', 'string', 'max:180', 'alpha_dash:ascii', Rule::unique('products', 'slug')->ignore($product?->id)],
            'name' => ['required', 'string', 'max:200'],
            'japanese_name' => ['nullable', 'string', 'max:200'],
            ...$this->translationRules('short_description', 500),
            ...$this->translationRules('description', 20000),
            'status' => ['required', Rule::enum(ProductStatus::class)],
            'is_featured' => ['boolean'],
            'published_at' => ['nullable', 'date'],

            'halal_status' => ['required', Rule::enum(HalalStatus::class)],
            ...$this->translationRules('halal_notes', 1000),
            'halal_certification_ids' => ['nullable', 'array', 'required_if:halal_status,'.HalalStatus::Certified->value],
            'halal_certification_ids.*' => ['integer', 'distinct', Rule::exists('halal_certifications', 'id')->whereNull('deleted_at')],

            ...$this->translationRules('ingredients', 5000),
            'allergens' => ['nullable', 'array'],
            'allergens.*' => [Rule::enum(Allergen::class)],
            'nutrition' => ['nullable', 'array'],
            'nutrition.basis' => ['nullable', 'string', 'max:40'],
            'nutrition.energy_kcal' => ['nullable', 'numeric', 'min:0', 'max:100000'],
            'nutrition.protein_g' => ['nullable', 'numeric', 'min:0', 'max:100000'],
            'nutrition.fat_g' => ['nullable', 'numeric', 'min:0', 'max:100000'],
            'nutrition.carbohydrate_g' => ['nullable', 'numeric', 'min:0', 'max:100000'],
            'nutrition.salt_g' => ['nullable', 'numeric', 'min:0', 'max:100000'],
            ...$this->translationRules('storage_instructions', 1000),
            'storage_type' => ['required', Rule::enum(StorageType::class)],
            'country_of_origin' => ['nullable', 'string', 'size:2', 'alpha:ascii'],
            'manufacturer' => ['nullable', 'string', 'max:255'],
            'importer' => ['nullable', 'string', 'max:255'],
            'net_content' => ['nullable', 'string', 'max:60'],
            'food_label_reviewed' => ['boolean'],

            'min_order_quantity' => ['required', 'integer', 'min:1', 'max:999'],
            'max_order_quantity' => ['nullable', 'integer', 'gte:min_order_quantity', 'max:999'],
            ...$this->translationRules('meta_title', 120),
            ...$this->translationRules('meta_description', 300),
        ];

        if (! $product) {
            $rules += [
                'variant' => ['required', 'array'],
                'variant.sku' => ['required', 'string', 'max:64', 'regex:/^[A-Z0-9][A-Z0-9._-]*$/', Rule::unique('product_variants', 'sku')],
                'variant.barcode' => ['nullable', 'digits_between:8,14'],
                'variant.price' => ['required', 'integer', 'min:0', 'max:'.self::MAX_PRICE],
                'variant.compare_at_price' => ['nullable', 'integer', 'gt:variant.price', 'max:'.self::MAX_PRICE],
                'variant.cost_price' => ['nullable', 'integer', 'min:0', 'max:'.self::MAX_PRICE],
                'variant.weight_grams' => ['nullable', 'integer', 'min:0', 'max:1000000'],
                'images' => ['nullable', 'array', 'max:10'],
                'images.*' => ['image', 'mimes:'.implode(',', config('shop.uploads.image_mimes')), 'max:'.config('shop.uploads.image_max_kb'), 'dimensions:max_width='.$maxDimension.',max_height='.$maxDimension],
            ];
        }

        return $rules;
    }

    /**
     * @return array<string, mixed>
     */
    public function productAttributes(): array
    {
        $nutrition = collect($this->validated('nutrition') ?? [])
            ->only(Product::NUTRITION_FIELDS)
            ->map(fn ($value, string $key) => $key === 'basis' ? (filled($value) ? trim((string) $value) : null) : (is_numeric($value) ? $value + 0 : null))
            ->filter(fn ($value) => $value !== null)
            ->all();

        return [
            'category_id' => (int) $this->validated('category_id'),
            'brand_id' => $this->validated('brand_id'),
            'supplier_id' => $this->validated('supplier_id'),
            'tax_class_id' => (int) $this->validated('tax_class_id'),
            'sku' => $this->validated('sku'),
            'slug' => $this->validated('slug'),
            'name' => $this->validated('name'),
            'japanese_name' => $this->validated('japanese_name'),
            'short_description' => $this->translation('short_description'),
            'description' => $this->translation('description'),
            'status' => $this->validated('status'),
            'is_featured' => $this->boolean('is_featured'),
            'published_at' => $this->validated('published_at') ? local_time_input($this->validated('published_at')) : null,
            'halal_status' => $this->validated('halal_status'),
            'halal_notes' => $this->translation('halal_notes'),
            'ingredients' => $this->translation('ingredients'),
            'allergens' => array_values(array_unique($this->validated('allergens') ?? [])) ?: null,
            'nutrition' => $nutrition === [] ? null : $nutrition,
            'storage_instructions' => $this->translation('storage_instructions'),
            'storage_type' => $this->validated('storage_type'),
            'country_of_origin' => $this->validated('country_of_origin'),
            'manufacturer' => $this->validated('manufacturer'),
            'importer' => $this->validated('importer'),
            'net_content' => $this->validated('net_content'),
            'min_order_quantity' => (int) $this->validated('min_order_quantity'),
            'max_order_quantity' => $this->validated('max_order_quantity'),
            'meta_title' => $this->translation('meta_title'),
            'meta_description' => $this->translation('meta_description'),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function variantAttributes(): array
    {
        $variant = $this->validated('variant');

        return [
            'sku' => $variant['sku'],
            'barcode' => $variant['barcode'] ?? null,
            'price' => (int) $variant['price'],
            'compare_at_price' => isset($variant['compare_at_price']) ? (int) $variant['compare_at_price'] : null,
            'cost_price' => isset($variant['cost_price']) ? (int) $variant['cost_price'] : null,
            'weight_grams' => isset($variant['weight_grams']) ? (int) $variant['weight_grams'] : null,
            'is_active' => true,
        ];
    }

    /**
     * @return list<int>
     */
    public function certificationIds(): array
    {
        return array_map('intval', $this->validated('halal_certification_ids') ?? []);
    }
}
