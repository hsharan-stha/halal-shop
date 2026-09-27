<?php

namespace App\Http\Requests\Admin;

use App\Models\Shop;
use App\Rules\JapanesePhone;
use App\Support\Prefectures;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class HalalShopRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('shops.manage') ?? false;
    }

    protected function prepareForValidation(): void
    {
        $digits = preg_replace('/\D/', '', (string) $this->input('postal_code')) ?? '';

        if (strlen($digits) === 7) {
            $this->merge(['postal_code' => substr($digits, 0, 3).'-'.substr($digits, 3)]);
        }

        if (is_string($this->input('phone')) && $this->input('phone') !== '') {
            $this->merge(['phone' => JapanesePhone::normalize($this->input('phone'))]);
        }

        $email = trim((string) $this->input('user_email'));
        $this->merge([
            'email' => trim((string) $this->input('email')) ?: null,
            'user_email' => $email === '' ? null : mb_strtolower($email),
            'is_active' => $this->boolean('is_active'),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        /** @var Shop|null $shop */
        $shop = $this->route('halal_shop');
        $hasLogin = $shop?->users()->exists() ?? false;

        return [
            'name' => ['required', 'string', 'max:120'],
            'phone' => ['nullable', 'string', new JapanesePhone],
            'email' => ['nullable', 'email:rfc', 'max:255'],
            'postal_code' => ['required', 'string', 'regex:/^\d{3}-\d{4}$/'],
            'prefecture' => ['required', 'string', Rule::in(Prefectures::names())],
            'city' => ['required', 'string', 'max:80'],
            'town' => ['required', 'string', 'max:80'],
            'street' => ['required', 'string', 'max:80'],
            'building' => ['nullable', 'string', 'max:80'],
            'latitude' => ['required', 'numeric', 'between:20,46'],
            'longitude' => ['required', 'numeric', 'between:122,154'],
            'is_active' => ['boolean'],
            'user_name' => [$hasLogin ? 'nullable' : 'required', 'string', 'max:100'],
            'user_email' => [$hasLogin ? 'nullable' : 'required', 'email:rfc', 'max:255', Rule::unique('users', 'email')],
            'user_password' => [$hasLogin ? 'nullable' : 'required', 'confirmed', Password::defaults()],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'name' => __('admin.halal_shops.name'),
            'phone' => __('shop.fields.phone'),
            'email' => __('shop.fields.email'),
            'postal_code' => __('shop.checkout.postal_code'),
            'prefecture' => __('shop.checkout.prefecture'),
            'city' => __('shop.checkout.city'),
            'town' => __('shop.checkout.town'),
            'street' => __('shop.checkout.street'),
            'building' => __('shop.checkout.building'),
            'latitude' => __('admin.halal_shops.latitude'),
            'longitude' => __('admin.halal_shops.longitude'),
            'user_name' => __('admin.halal_shops.login_name'),
            'user_email' => __('admin.halal_shops.login_email'),
            'user_password' => __('shop.fields.password'),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function shopAttributes(): array
    {
        return [
            'name' => $this->validated('name'),
            'phone' => $this->validated('phone'),
            'email' => $this->validated('email'),
            'postal_code' => $this->validated('postal_code'),
            'prefecture' => $this->validated('prefecture'),
            'city' => $this->validated('city'),
            'town' => $this->validated('town'),
            'street' => $this->validated('street'),
            'building' => $this->validated('building'),
            'latitude' => $this->validated('latitude'),
            'longitude' => $this->validated('longitude'),
            'is_active' => (bool) $this->validated('is_active'),
        ];
    }
}
