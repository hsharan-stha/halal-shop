<?php

namespace App\Livewire\Admin;

use App\Http\Requests\Admin\ProductRequest;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Locked;
use Livewire\Component;

class ProductVariants extends Component
{
    #[Locked]
    public Product $product;

    #[Locked]
    public ?int $editingId = null;

    public bool $showForm = false;

    public string $sku = '';

    public string $barcode = '';

    public string $name_ja = '';

    public string $name_en = '';

    public ?string $price = null;

    public ?string $compare_at_price = null;

    public ?string $cost_price = null;

    public ?string $weight_grams = null;

    public bool $is_active = true;

    public function create(): void
    {
        Gate::authorize('products.update');

        $this->resetForm();
        $this->sku = Str::upper($this->product->sku.'-'.($this->product->variants()->withTrashed()->count() + 1));
        $this->showForm = true;
    }

    public function edit(int $id): void
    {
        Gate::authorize('products.update');

        $variant = $this->variant($id);
        $this->editingId = $variant->id;
        $this->sku = $variant->sku;
        $this->barcode = (string) $variant->barcode;
        $this->name_ja = (string) ($variant->name['ja'] ?? '');
        $this->name_en = (string) ($variant->name['en'] ?? '');
        $this->price = (string) $variant->price;
        $this->compare_at_price = $variant->compare_at_price !== null ? (string) $variant->compare_at_price : null;
        $this->cost_price = $variant->cost_price !== null ? (string) $variant->cost_price : null;
        $this->weight_grams = $variant->weight_grams !== null ? (string) $variant->weight_grams : null;
        $this->is_active = $variant->is_active;
        $this->showForm = true;
    }

    public function save(): void
    {
        Gate::authorize('products.update');

        $this->sku = Str::upper(trim($this->sku));
        $max = ProductRequest::MAX_PRICE;

        $data = $this->validate([
            'sku' => ['required', 'string', 'max:64', 'regex:/^[A-Z0-9][A-Z0-9._-]*$/', Rule::unique('product_variants', 'sku')->ignore($this->editingId)],
            'barcode' => ['nullable', 'digits_between:8,14'],
            'name_ja' => ['nullable', 'string', 'max:120'],
            'name_en' => ['nullable', 'string', 'max:120'],
            'price' => ['required', 'integer', 'min:0', 'max:'.$max],
            'compare_at_price' => ['nullable', 'integer', 'gt:price', 'max:'.$max],
            'cost_price' => ['nullable', 'integer', 'min:0', 'max:'.$max],
            'weight_grams' => ['nullable', 'integer', 'min:0', 'max:1000000'],
            'is_active' => ['boolean'],
        ], attributes: [
            'sku' => __('admin.variants.sku'),
            'price' => __('admin.variants.price'),
            'compare_at_price' => __('admin.variants.compare_at_price'),
        ]);

        $variant = $this->editingId ? $this->variant($this->editingId) : new ProductVariant(['sort_order' => (int) $this->product->variants()->max('sort_order') + 1]);

        if ($variant->is_default && ! $data['is_active']) {
            $this->addError('is_active', __('admin.variants.default_must_be_active'));

            return;
        }

        $name = array_filter(['ja' => trim($data['name_ja'] ?? ''), 'en' => trim($data['name_en'] ?? '')]);

        $variant->fill([
            'sku' => $data['sku'],
            'barcode' => filled($data['barcode']) ? $data['barcode'] : null,
            'name' => $name === [] ? null : $name,
            'price' => (int) $data['price'],
            'compare_at_price' => filled($data['compare_at_price']) ? (int) $data['compare_at_price'] : null,
            'cost_price' => filled($data['cost_price']) ? (int) $data['cost_price'] : null,
            'weight_grams' => filled($data['weight_grams']) ? (int) $data['weight_grams'] : null,
            'is_active' => $data['is_active'],
        ]);

        $this->product->variants()->save($variant);

        $this->resetForm();
        $this->dispatch('toast', type: 'success', message: __('admin.variants.saved'));
    }

    public function setDefault(int $id): void
    {
        Gate::authorize('products.update');

        $variant = $this->variant($id);

        if (! $variant->is_active) {
            $this->dispatch('toast', type: 'error', message: __('admin.variants.default_must_be_active'));

            return;
        }

        DB::transaction(function () use ($variant): void {
            $this->product->variants()->whereKeyNot($variant->id)->where('is_default', true)->get()->each(fn (ProductVariant $other) => $other->forceFill(['is_default' => false])->save());
            $variant->forceFill(['is_default' => true])->save();
        });

        $this->dispatch('toast', type: 'success', message: __('admin.variants.saved'));
    }

    public function delete(int $id): void
    {
        Gate::authorize('products.update');

        $variant = $this->variant($id);

        if ($variant->is_default) {
            $this->dispatch('toast', type: 'error', message: __('admin.variants.cannot_delete_default'));

            return;
        }

        $variant->delete();

        if ($this->editingId === $id) {
            $this->resetForm();
        }

        $this->dispatch('toast', type: 'success', message: __('admin.variants.deleted'));
    }

    public function reorder(int|string $item, int $position): void
    {
        Gate::authorize('products.update');

        $ids = $this->product->variants()->pluck('id')->all();

        if (! in_array((int) $item, $ids, true)) {
            return;
        }

        $ids = array_values(array_diff($ids, [(int) $item]));
        array_splice($ids, max(0, $position), 0, [(int) $item]);

        DB::transaction(function () use ($ids): void {
            foreach ($ids as $index => $id) {
                ProductVariant::query()->whereKey($id)->update(['sort_order' => $index]);
            }
        });
    }

    public function cancel(): void
    {
        $this->resetForm();
    }

    public function render(): View
    {
        return view('livewire.admin.product-variants', [
            'variants' => $this->product->variants()->get(),
        ]);
    }

    private function variant(int $id): ProductVariant
    {
        return $this->product->variants()->whereKey($id)->firstOrFail();
    }

    private function resetForm(): void
    {
        $this->reset(['editingId', 'showForm', 'sku', 'barcode', 'name_ja', 'name_en', 'price', 'compare_at_price', 'cost_price', 'weight_grams', 'is_active']);
        $this->resetValidation();
    }
}
