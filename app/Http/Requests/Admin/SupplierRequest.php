<?php

namespace App\Http\Requests\Admin;

use App\Models\Supplier;
use App\Rules\JapanesePhone;
use App\Rules\JapanesePostalCode;
use App\Support\Prefectures;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class SupplierRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('suppliers.manage');
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'code' => $this->filled('code') ? Str::upper(trim((string) $this->input('code'))) : null,
            'country_code' => Str::upper((string) ($this->input('country_code') ?: 'JP')),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        /** @var Supplier|null $supplier */
        $supplier = $this->route('supplier');
        $domestic = $this->input('country_code') === 'JP';

        return [
            'name' => ['required', 'string', 'max:120'],
            'company_name' => ['nullable', 'string', 'max:160'],
            'code' => ['nullable', 'string', 'max:40', 'regex:/^[A-Z0-9][A-Z0-9_-]*$/', Rule::unique('suppliers', 'code')->ignore($supplier?->id)],
            'contact_name' => ['nullable', 'string', 'max:120'],
            'email' => ['nullable', 'email:rfc', 'max:255'],
            'phone' => ['nullable', 'string', 'max:30', $domestic ? new JapanesePhone : 'regex:/^[0-9+\-() ]+$/'],
            'country_code' => ['required', 'string', 'size:2', 'alpha:ascii'],
            'postal_code' => ['nullable', 'string', 'max:10', ...($domestic ? [new JapanesePostalCode] : [])],
            'prefecture' => ['nullable', 'string', ...($domestic ? [Rule::in(Prefectures::names())] : ['max:20'])],
            'address' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'is_active' => ['boolean'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function attributesForModel(): array
    {
        $domestic = $this->validated('country_code') === 'JP';

        return [
            'name' => $this->validated('name'),
            'company_name' => $this->validated('company_name'),
            'code' => $this->validated('code'),
            'contact_name' => $this->validated('contact_name'),
            'email' => $this->validated('email'),
            'phone' => $this->validated('phone') && $domestic ? JapanesePhone::normalize($this->validated('phone')) : $this->validated('phone'),
            'country_code' => $this->validated('country_code'),
            'postal_code' => $this->validated('postal_code') && $domestic ? JapanesePostalCode::normalize($this->validated('postal_code')) : $this->validated('postal_code'),
            'prefecture' => $this->validated('prefecture'),
            'address' => $this->validated('address'),
            'notes' => $this->validated('notes'),
            'is_active' => $this->boolean('is_active'),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'name' => __('admin.suppliers.name'),
            'company_name' => __('admin.suppliers.company_name'),
            'code' => __('admin.suppliers.code'),
            'contact_name' => __('admin.suppliers.contact_name'),
            'email' => __('admin.suppliers.email'),
            'phone' => __('admin.suppliers.phone'),
            'country_code' => __('admin.suppliers.country'),
            'postal_code' => __('admin.suppliers.postal_code'),
            'prefecture' => __('admin.suppliers.prefecture'),
            'address' => __('admin.suppliers.address'),
        ];
    }
}
