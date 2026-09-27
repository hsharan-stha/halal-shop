<?php

namespace App\Http\Requests\Account;

use App\Rules\JapanesePhone;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'string', 'email:rfc', 'max:255', Rule::unique('users', 'email')->ignore($this->user()->id)],
            'phone' => ['nullable', 'string', new JapanesePhone],
            'date_of_birth' => ['nullable', 'date', 'before:today', 'after:1900-01-01'],
            'locale' => ['required', Rule::in(array_keys(config('shop.locales')))],
            'photo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:4096', 'dimensions:max_width=4000,max_height=4000'],
            'remove_photo' => ['nullable', 'boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['email' => mb_strtolower(trim((string) $this->input('email')))]);
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'name' => __('shop.fields.name'),
            'email' => __('shop.fields.email'),
            'phone' => __('shop.fields.phone'),
            'date_of_birth' => __('shop.fields.date_of_birth'),
            'photo' => __('shop.fields.profile_photo'),
        ];
    }
}
