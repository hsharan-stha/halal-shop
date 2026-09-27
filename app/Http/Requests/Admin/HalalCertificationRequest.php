<?php

namespace App\Http\Requests\Admin;

use App\Models\HalalCertification;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class HalalCertificationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('halal_certificates.manage');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        /** @var HalalCertification|null $certification */
        $certification = $this->route('certification');

        return [
            'brand_id' => ['nullable', 'integer', Rule::exists('brands', 'id')->whereNull('deleted_at')],
            'certifying_body' => ['required', 'string', 'max:150'],
            'certificate_number' => [
                'required', 'string', 'max:100',
                Rule::unique('halal_certifications', 'certificate_number')
                    ->where('certifying_body', (string) $this->input('certifying_body'))
                    ->ignore($certification?->id),
            ],
            'scope' => ['nullable', 'string', 'max:255'],
            'issued_at' => ['nullable', 'date', 'before_or_equal:expires_at'],
            'expires_at' => ['required', 'date', 'after_or_equal:issued_at'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'file' => [
                $certification ? 'nullable' : 'required',
                'file',
                'mimes:'.implode(',', config('shop.uploads.document_mimes')),
                'mimetypes:application/pdf,image/jpeg,image/png,image/webp',
                'max:'.config('shop.uploads.document_max_kb'),
            ],
            'product_ids' => ['nullable', 'array', 'max:500'],
            'product_ids.*' => ['integer', 'distinct', Rule::exists('products', 'id')->whereNull('deleted_at')],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function attributesForModel(): array
    {
        return $this->safe()->only(['brand_id', 'certifying_body', 'certificate_number', 'scope', 'issued_at', 'expires_at', 'notes']);
    }

    /**
     * @return list<int>
     */
    public function productIds(): array
    {
        return array_map('intval', $this->validated('product_ids') ?? []);
    }
}
