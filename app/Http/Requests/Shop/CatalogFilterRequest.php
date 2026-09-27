<?php

namespace App\Http\Requests\Shop;

use App\Enums\HalalStatus;
use App\Enums\StorageType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CatalogFilterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'q' => trim((string) $this->input('q', '')) ?: null,
            'country' => $this->filled('country') ? strtoupper((string) $this->input('country')) : null,
            'sort' => $this->input('sort') ?: 'newest',
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'q' => ['nullable', 'string', 'max:100'],
            'category' => ['nullable', 'string', 'max:120'],
            'brand' => ['nullable', 'string', 'max:120'],
            'halal' => ['nullable', Rule::enum(HalalStatus::class)],
            'storage' => ['nullable', Rule::enum(StorageType::class)],
            'country' => ['nullable', 'string', 'size:2', 'alpha:ascii'],
            'availability' => ['nullable', Rule::in(['in_stock', 'out_of_stock'])],
            'min_price' => ['nullable', 'integer', 'min:0', 'max:10000000'],
            'max_price' => ['nullable', 'integer', 'min:0', 'max:10000000'],
            'sort' => ['required', Rule::in(['newest', 'price_asc', 'price_desc', 'name'])],
        ];
    }

    /**
     * @return array{q: ?string, category: ?string, brand: ?string, halal: ?string, storage: ?string, country: ?string, availability: ?string, min_price: ?int, max_price: ?int, sort: string}
     */
    public function filters(): array
    {
        $data = $this->validated();
        $min = isset($data['min_price']) ? (int) $data['min_price'] : null;
        $max = isset($data['max_price']) ? (int) $data['max_price'] : null;

        if ($min !== null && $max !== null && $min > $max) {
            [$min, $max] = [$max, $min];
        }

        return [
            'q' => $data['q'] ?? null,
            'category' => $data['category'] ?? null,
            'brand' => $data['brand'] ?? null,
            'halal' => $data['halal'] ?? null,
            'storage' => $data['storage'] ?? null,
            'country' => $data['country'] ?? null,
            'availability' => $data['availability'] ?? null,
            'min_price' => $min,
            'max_price' => $max,
            'sort' => $data['sort'],
        ];
    }
}
