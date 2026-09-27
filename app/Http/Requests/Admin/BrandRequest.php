<?php

namespace App\Http\Requests\Admin;

use App\Http\Requests\Concerns\HandlesTranslations;
use App\Models\Brand;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class BrandRequest extends FormRequest
{
    use HandlesTranslations;

    public function authorize(): bool
    {
        return $this->user()->can('brands.manage');
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'slug' => Str::slug((string) ($this->input('slug') ?: $this->input('name'))),
            'country_of_origin' => $this->filled('country_of_origin') ? Str::upper((string) $this->input('country_of_origin')) : null,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        /** @var Brand|null $brand */
        $brand = $this->route('brand');

        return [
            'name' => ['required', 'string', 'max:120'],
            'japanese_name' => ['nullable', 'string', 'max:120'],
            'slug' => ['required', 'string', 'max:140', 'alpha_dash:ascii', Rule::unique('brands', 'slug')->ignore($brand?->id)],
            ...$this->translationRules('description', 2000),
            'country_of_origin' => ['nullable', 'string', 'size:2', 'alpha:ascii'],
            'website_url' => ['nullable', 'url:http,https', 'max:255'],
            'logo' => ['nullable', 'image', 'mimes:'.implode(',', config('shop.uploads.image_mimes')), 'max:'.config('shop.uploads.image_max_kb'), 'dimensions:max_width='.config('shop.uploads.image_max_dimension').',max_height='.config('shop.uploads.image_max_dimension')],
            'logo_remove' => ['boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:100000'],
            'is_active' => ['boolean'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function attributesForModel(): array
    {
        return [
            'name' => $this->validated('name'),
            'japanese_name' => $this->validated('japanese_name'),
            'slug' => $this->validated('slug'),
            'description' => $this->translation('description'),
            'country_of_origin' => $this->validated('country_of_origin'),
            'website_url' => $this->validated('website_url'),
            'sort_order' => (int) $this->validated('sort_order'),
            'is_active' => $this->boolean('is_active'),
        ];
    }
}
