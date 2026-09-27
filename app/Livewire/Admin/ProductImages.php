<?php

namespace App\Livewire\Admin;

use App\Models\Product;
use App\Models\ProductImage;
use App\Services\Catalog\ProductService;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;

class ProductImages extends Component
{
    use WithFileUploads;

    public const MAX_IMAGES = 20;

    #[Locked]
    public Product $product;

    /**
     * @var array<int, TemporaryUploadedFile>
     */
    public array $uploads = [];

    /**
     * @var array<int, array{ja: string, en: string, variant: string}>
     */
    public array $meta = [];

    public function mount(): void
    {
        $this->loadMeta();
    }

    public function updatedUploads(ProductService $service): void
    {
        Gate::authorize('products.update');

        $dimension = config('shop.uploads.image_max_dimension');
        $remaining = self::MAX_IMAGES - $this->product->images()->count();

        $this->validate([
            'uploads' => ['array', 'max:'.max(0, $remaining)],
            'uploads.*' => ['image', 'mimes:'.implode(',', config('shop.uploads.image_mimes')), 'max:'.config('shop.uploads.image_max_kb'), 'dimensions:max_width='.$dimension.',max_height='.$dimension],
        ], attributes: ['uploads' => __('admin.images.title'), 'uploads.*' => __('admin.images.title')]);

        foreach ($this->uploads as $file) {
            $service->addImage($this->product, $file);
        }

        $this->uploads = [];
        $this->loadMeta();
        $this->dispatch('toast', type: 'success', message: __('admin.images.uploaded'));
    }

    public function saveMeta(int $id): void
    {
        Gate::authorize('products.update');

        $image = $this->image($id);

        $this->validate([
            "meta.$id.ja" => ['nullable', 'string', 'max:200'],
            "meta.$id.en" => ['nullable', 'string', 'max:200'],
            "meta.$id.variant" => ['nullable', 'integer'],
        ]);

        $variantId = filled($this->meta[$id]['variant'] ?? null) ? (int) $this->meta[$id]['variant'] : null;

        if ($variantId && ! $this->product->variants()->whereKey($variantId)->exists()) {
            $variantId = null;
        }

        $alt = array_filter(['ja' => trim($this->meta[$id]['ja'] ?? ''), 'en' => trim($this->meta[$id]['en'] ?? '')]);
        $image->forceFill(['alt' => $alt === [] ? null : $alt, 'product_variant_id' => $variantId])->save();

        $this->dispatch('toast', type: 'success', message: __('admin.images.saved'));
    }

    public function delete(int $id): void
    {
        Gate::authorize('products.update');

        $this->image($id)->delete();
        unset($this->meta[$id]);

        $this->dispatch('toast', type: 'success', message: __('admin.images.deleted'));
    }

    public function reorder(int|string $item, int $position): void
    {
        Gate::authorize('products.update');

        $ids = $this->product->images()->pluck('id')->all();

        if (! in_array((int) $item, $ids, true)) {
            return;
        }

        $ids = array_values(array_diff($ids, [(int) $item]));
        array_splice($ids, max(0, $position), 0, [(int) $item]);

        DB::transaction(function () use ($ids): void {
            foreach ($ids as $index => $id) {
                ProductImage::query()->whereKey($id)->update(['sort_order' => $index]);
            }
        });
    }

    public function render(): View
    {
        $images = $this->product->images()->get();

        return view('livewire.admin.product-images', [
            'images' => $images,
            'processing' => $images->contains(fn (ProductImage $image) => ! $image->isProcessed()),
            'variants' => $this->product->variants()->get()->mapWithKeys(fn ($variant) => [$variant->id => $variant->label().' · '.$variant->sku])->all(),
        ]);
    }

    private function image(int $id): ProductImage
    {
        return $this->product->images()->whereKey($id)->firstOrFail();
    }

    private function loadMeta(): void
    {
        $this->meta = $this->product->images()->get()->mapWithKeys(fn (ProductImage $image) => [$image->id => [
            'ja' => (string) ($image->alt['ja'] ?? ''),
            'en' => (string) ($image->alt['en'] ?? ''),
            'variant' => (string) ($image->product_variant_id ?? ''),
        ]])->all();
    }
}
