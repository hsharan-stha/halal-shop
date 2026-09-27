<?php

namespace App\Http\Requests\Shop;

use App\Enums\DeliveryDestination;
use App\Enums\PaymentMethod;
use App\Rules\JapanesePhone;
use App\Support\Prefectures;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CheckoutRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    protected function prepareForValidation(): void
    {
        $digits = preg_replace('/\D/', '', (string) $this->input('postal_code')) ?? '';

        if (strlen($digits) === 7) {
            $this->merge(['postal_code' => substr($digits, 0, 3).'-'.substr($digits, 3)]);
        }

        if (! $this->filled('delivery_to')) {
            $this->merge(['delivery_to' => DeliveryDestination::Customer->value]);
        }

        if (is_string($this->input('phone')) && $this->input('phone') !== '') {
            $this->merge(['phone' => JapanesePhone::normalize($this->input('phone'))]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $toShop = $this->input('delivery_to') === DeliveryDestination::Shop->value;
        $address = $toShop ? 'nullable' : 'required';

        return [
            'delivery_to' => ['required', Rule::enum(DeliveryDestination::class)],
            'pickup_shop_id' => [$toShop ? 'required' : 'nullable', 'integer', Rule::exists('shops', 'id')->where('is_active', true)],
            'recipient_name' => ['required', 'string', 'max:255'],
            'phone' => ['required', 'string', new JapanesePhone],
            'postal_code' => [$address, 'string', 'regex:/^\d{3}-\d{4}$/'],
            'prefecture' => [$address, 'string', Rule::in(Prefectures::names())],
            'city' => [$address, 'string', 'max:80'],
            'ward' => ['nullable', 'string', 'max:80'],
            'town' => [$address, 'string', 'max:80'],
            'street' => [$address, 'string', 'max:80'],
            'building' => ['nullable', 'string', 'max:80'],
            'room' => ['nullable', 'string', 'max:40'],
            'customer_note' => ['nullable', 'string', 'max:500'],
            'payment_method' => ['required', Rule::enum(PaymentMethod::class)],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'recipient_name' => __('shop.checkout.recipient'),
            'phone' => __('shop.fields.phone'),
            'postal_code' => __('shop.checkout.postal_code'),
            'prefecture' => __('shop.checkout.prefecture'),
            'city' => __('shop.checkout.city'),
            'ward' => __('shop.checkout.ward'),
            'town' => __('shop.checkout.town'),
            'street' => __('shop.checkout.street'),
            'building' => __('shop.checkout.building'),
            'room' => __('shop.checkout.room'),
            'customer_note' => __('shop.checkout.note'),
            'payment_method' => __('shop.checkout.payment'),
        ];
    }

    /**
     * @return array{recipient_name: string, phone: string, postal_code: string, prefecture: string, city: string, ward: ?string, town: string, street: string, building: ?string, room: ?string}
     */
    public function address(): array
    {
        return [
            'recipient_name' => $this->validated('recipient_name'),
            'phone' => $this->validated('phone'),
            'postal_code' => $this->validated('postal_code'),
            'prefecture' => $this->validated('prefecture'),
            'city' => $this->validated('city'),
            'ward' => $this->validated('ward'),
            'town' => $this->validated('town'),
            'street' => $this->validated('street'),
            'building' => $this->validated('building'),
            'room' => $this->validated('room'),
        ];
    }
}
