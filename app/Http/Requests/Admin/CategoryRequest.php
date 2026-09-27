<?php

namespace App\Http\Requests\Admin;

use App\Http\Requests\Concerns\HandlesTranslations;
use App\Models\Category;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class CategoryRequest extends FormRequest
{
    use HandlesTranslations;

    public function authorize(): bool
    {
        return $this->user()->can('categories.manage');
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['slug' => Str::slug((string) ($this->input('slug') ?: $this->input('name')))]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        /** @var Category|null $category */
        $category = $this->route('category');

        return [
            'parent_id' => ['nullable', 'integer', Rule::exists('categories', 'id'), function (string $attribute, mixed $value, Closure $fail) use ($category): void {
                if ($category && in_array((int) $value, $category->descendantAndSelfIds(), true)) {
                    $fail(__('admin.categories.invalid_parent'));
                }
            }],
            'name' => ['required', 'string', 'max:120'],
            'japanese_name' => ['nullable', 'string', 'max:120'],
            'slug' => ['required', 'string', 'max:140', 'alpha_dash:ascii', Rule::unique('categories', 'slug')->ignore($category?->id)],
            ...$this->translationRules('description', 2000),
            'icon' => ['nullable', 'string', Rule::in(Category::ICONS)],
            'image' => ['nullable', 'image', 'mimes:'.implode(',', config('shop.uploads.image_mimes')), 'max:'.config('shop.uploads.image_max_kb'), 'dimensions:max_width='.config('shop.uploads.image_max_dimension').',max_height='.config('shop.uploads.image_max_dimension')],
            'image_remove' => ['boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:100000'],
            'is_active' => ['boolean'],
            ...$this->translationRules('meta_title', 120),
            ...$this->translationRules('meta_description', 300),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function attributesForModel(): array
    {
        return [
            'parent_id' => $this->validated('parent_id'),
            'name' => $this->validated('name'),
            'japanese_name' => $this->validated('japanese_name'),
            'slug' => $this->validated('slug'),
            'description' => $this->translation('description'),
            'icon' => $this->validated('icon'),
            'sort_order' => (int) $this->validated('sort_order'),
            'is_active' => $this->boolean('is_active'),
            'meta_title' => $this->translation('meta_title'),
            'meta_description' => $this->translation('meta_description'),
        ];
    }
}
