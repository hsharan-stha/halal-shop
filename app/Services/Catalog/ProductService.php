<?php

namespace App\Services\Catalog;

use App\Enums\HalalStatus;
use App\Enums\ProductStatus;
use App\Jobs\GenerateProductImageRenditions;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\ProductVariant;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\Media\ImageStorage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ProductService
{
    public function __construct(
        private readonly ImageStorage $images,
        private readonly AuditLogger $auditLogger,
    ) {}

    /**
     * @param  array<string, mixed>  $data  validated product attributes
     * @param  array<string, mixed>  $variant  validated default variant attributes
     * @param  list<int>  $certificationIds
     */
    public function create(array $data, array $variant, array $certificationIds, User $actor, bool $labelReviewed): Product
    {
        return DB::transaction(function () use ($data, $variant, $certificationIds, $actor, $labelReviewed): Product {
            $product = new Product($data);
            $this->applyPublication($product);

            if ($labelReviewed) {
                $product->forceFill(['food_label_reviewed_at' => now(), 'food_label_reviewed_by' => $actor->id]);
            }

            $product->save();

            $default = new ProductVariant($variant);
            $default->is_default = true;
            $product->variants()->save($default);

            $product->halalCertifications()->sync($certificationIds);

            return $product;
        });
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  list<int>  $certificationIds
     */
    public function update(Product $product, array $data, array $certificationIds, User $actor, bool $labelReviewed): Product
    {
        return DB::transaction(function () use ($product, $data, $certificationIds, $actor, $labelReviewed): Product {
            $product->fill($data);
            $this->applyPublication($product);

            if ($labelReviewed) {
                $product->forceFill(['food_label_reviewed_at' => now(), 'food_label_reviewed_by' => $actor->id]);
            }

            $product->save();

            $changes = $product->halalCertifications()->sync($certificationIds);

            if (array_filter($changes)) {
                $this->auditLogger->log('product.certifications_synced', $product, null, $changes);
            }

            return $product;
        });
    }

    /**
     * Copy a product as a new draft. Halal status and the food label review are
     * reset because they must be confirmed explicitly for every product.
     */
    public function duplicate(Product $source): Product
    {
        $source->load(['variants', 'images']);

        return DB::transaction(function () use ($source): Product {
            $copy = $source->replicate(['food_label_reviewed_at', 'food_label_reviewed_by', 'published_at']);
            $copy->forceFill([
                'sku' => $this->uniqueSku($source->sku.'-COPY'),
                'slug' => $this->uniqueSlug($source->slug.'-copy'),
                'name' => $source->name.' ('.__('admin.products.copy_suffix').')',
                'japanese_name' => $source->japanese_name ? $source->japanese_name.'（'.__('admin.products.copy_suffix', [], 'ja').'）' : null,
                'status' => ProductStatus::Draft,
                'is_featured' => false,
                'halal_status' => HalalStatus::Unverified,
                'food_label_reviewed_at' => null,
                'food_label_reviewed_by' => null,
            ])->save();

            foreach ($source->variants as $variant) {
                $newVariant = $variant->replicate(['product_id']);
                $newVariant->sku = $this->uniqueSku($variant->sku.'-COPY', ProductVariant::class);
                $newVariant->barcode = null;
                $newVariant->is_default = $variant->is_default;
                $copy->variants()->save($newVariant);
            }

            foreach ($source->images as $image) {
                $newImage = $image->replicate(['product_id', 'product_variant_id', 'renditions']);
                $newImage->path = $this->images->copy($image->path, 'products/'.$copy->id);
                $newImage->renditions = null;
                $copy->images()->save($newImage);
                GenerateProductImageRenditions::dispatch($newImage)->afterCommit();
            }

            $this->auditLogger->log('product.duplicated', $copy, null, ['source_id' => $source->id]);

            return $copy;
        });
    }

    /**
     * @param  Collection<int, Product>  $products
     */
    public function bulkStatus(Collection $products, ProductStatus $status): int
    {
        return DB::transaction(function () use ($products, $status): int {
            foreach ($products as $product) {
                $product->status = $status;
                $this->applyPublication($product);
                $product->save();
            }

            return $products->count();
        });
    }

    /**
     * @param  Collection<int, Product>  $products
     */
    public function bulkDelete(Collection $products): int
    {
        return DB::transaction(function () use ($products): int {
            $products->each->delete();

            return $products->count();
        });
    }

    public function addImage(Product $product, UploadedFile $file): ProductImage
    {
        $stored = $this->images->store($file, 'products/'.$product->id);

        $image = $product->images()->create([
            'disk' => config('shop.media_disk'),
            'path' => $stored['path'],
            'width' => $stored['width'],
            'height' => $stored['height'],
            'sort_order' => (int) $product->images()->max('sort_order') + 1,
        ]);

        GenerateProductImageRenditions::dispatch($image);

        return $image;
    }

    public function uniqueSlug(string $base, ?int $ignoreId = null): string
    {
        $base = Str::slug($base) ?: Str::lower(Str::random(8));
        $slug = $base;
        $i = 2;

        while (Product::withTrashed()->where('slug', $slug)->when($ignoreId, fn ($q) => $q->whereKeyNot($ignoreId))->exists()) {
            $slug = $base.'-'.$i++;
        }

        return $slug;
    }

    /**
     * @param  class-string<Product|ProductVariant>  $model
     */
    private function uniqueSku(string $base, string $model = Product::class): string
    {
        $base = Str::upper(Str::limit($base, 56, ''));
        $sku = $base;
        $i = 2;

        while ($model::withTrashed()->where('sku', $sku)->exists()) {
            $sku = $base.'-'.$i++;
        }

        return $sku;
    }

    private function applyPublication(Product $product): void
    {
        if ($product->status === ProductStatus::Active && $product->published_at === null) {
            $product->published_at = now();
        }
    }
}
