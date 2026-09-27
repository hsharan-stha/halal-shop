<?php

namespace App\Support\Settings;

use Illuminate\Validation\Rule;

/**
 * Declarative catalogue of every runtime-configurable store setting.
 *
 * Each field: type, default, optional rules/options. Translatable fields store
 * an array keyed by locale (e.g. ['ja' => '...', 'en' => '...']).
 */
class SettingsRegistry
{
    public const TYPES = [
        'string', 'text', 'boolean', 'integer', 'color', 'email', 'url',
        'image', 'select', 'int_list', 'translatable_string', 'translatable_text',
    ];

    /**
     * @return array<string, array<string, array<string, mixed>>>
     */
    public static function groups(): array
    {
        return [
            'general' => [
                'company_name' => ['type' => 'string', 'default' => ''],
                'company_representative' => ['type' => 'string', 'default' => ''],
                'company_address' => ['type' => 'text', 'default' => ''],
                'company_phone' => ['type' => 'string', 'default' => ''],
                'business_hours' => ['type' => 'translatable_string', 'default' => ['ja' => '', 'en' => '']],
            ],
            'branding' => [
                'application_name' => ['type' => 'string', 'default' => 'Halal Shop', 'rules' => ['required', 'max:80']],
                'application_short_name' => ['type' => 'string', 'default' => 'Halal', 'rules' => ['required', 'max:24']],
                'logo' => ['type' => 'image', 'default' => null],
                'favicon' => ['type' => 'image', 'default' => null],
                'primary_color' => ['type' => 'color', 'default' => '#0f7a4f'],
                'secondary_color' => ['type' => 'color', 'default' => '#14532d'],
                'accent_color' => ['type' => 'color', 'default' => '#d97706'],
                'background_color' => ['type' => 'color', 'default' => '#f7f7f5'],
                'font_family' => ['type' => 'string', 'default' => '', 'rules' => ['nullable', 'max:60', 'regex:/^[A-Za-z0-9 \-]*$/']],
                'dark_mode_enabled' => ['type' => 'boolean', 'default' => true],
                'support_email' => ['type' => 'email', 'default' => 'support@example.com'],
                'support_phone' => ['type' => 'string', 'default' => '', 'rules' => ['nullable', 'max:20', 'regex:/^[0-9+\-() ]*$/']],
                'facebook_url' => ['type' => 'url', 'default' => ''],
                'instagram_url' => ['type' => 'url', 'default' => ''],
                'youtube_url' => ['type' => 'url', 'default' => ''],
                'google_business_url' => ['type' => 'url', 'default' => ''],
            ],
            'languages' => [
                'default_locale' => ['type' => 'select', 'default' => 'ja', 'options' => ['ja' => '日本語', 'en' => 'English']],
            ],
            'currency' => [
                'currency_code' => ['type' => 'select', 'default' => 'JPY', 'options' => ['JPY' => 'JPY (¥)']],
            ],
            'tax' => [
                'prices_include_tax' => ['type' => 'boolean', 'default' => true],
                'rounding' => ['type' => 'select', 'default' => 'floor', 'options' => ['floor' => 'floor', 'round' => 'round', 'ceil' => 'ceil']],
                'show_tax_breakdown' => ['type' => 'boolean', 'default' => true],
            ],
            'shipping' => [
                'free_shipping_threshold' => ['type' => 'integer', 'default' => 0, 'rules' => ['integer', 'min:0']],
                'flat_rate' => ['type' => 'integer', 'default' => 660, 'rules' => ['integer', 'min:0']],
                'frozen_surcharge' => ['type' => 'integer', 'default' => 440, 'rules' => ['integer', 'min:0']],
                'min_lead_days' => ['type' => 'integer', 'default' => 2, 'rules' => ['integer', 'min:0', 'max:30']],
                'max_days_ahead' => ['type' => 'integer', 'default' => 14, 'rules' => ['integer', 'min:1', 'max:60']],
                'pickup_address' => ['type' => 'translatable_text', 'default' => ['ja' => '', 'en' => '']],
            ],
            'payment' => [
                'bank_transfer_instructions' => ['type' => 'translatable_text', 'default' => ['ja' => '', 'en' => '']],
                'cod_fee' => ['type' => 'integer', 'default' => 330, 'rules' => ['integer', 'min:0']],
                'cod_max_amount' => ['type' => 'integer', 'default' => 300000, 'rules' => ['integer', 'min:0']],
                'bank_transfer_due_days' => ['type' => 'integer', 'default' => 7, 'rules' => ['integer', 'min:1', 'max:30']],
            ],
            'orders' => [
                'order_number_prefix' => ['type' => 'string', 'default' => 'HS', 'rules' => ['required', 'max:6', 'alpha_num']],
                'customer_cancellable_statuses' => ['type' => 'string', 'default' => 'pending,confirmed', 'rules' => ['required', 'max:100', 'regex:/^[a-z_,]+$/']],
                'refund_request_days' => ['type' => 'integer', 'default' => 7, 'rules' => ['integer', 'min:0', 'max:90']],
                'auto_cancel_unpaid_hours' => ['type' => 'integer', 'default' => 72, 'rules' => ['integer', 'min:1']],
            ],
            'inventory' => [
                'low_stock_threshold' => ['type' => 'integer', 'default' => 5, 'rules' => ['integer', 'min:0']],
                'allow_negative_stock' => ['type' => 'boolean', 'default' => false],
                'expiry_warning_days' => ['type' => 'int_list', 'default' => [30, 14, 7, 3]],
                'min_sellable_shelf_life_days' => ['type' => 'integer', 'default' => 1, 'rules' => ['integer', 'min:0', 'max:365']],
            ],
            'halal' => [
                'certificate_expiry_warning_days' => ['type' => 'int_list', 'default' => [60, 30, 14, 7]],
                'information_notice' => ['type' => 'translatable_text', 'default' => ['ja' => '', 'en' => '']],
                'hide_not_halal_products' => ['type' => 'boolean', 'default' => false],
            ],
            'notifications' => [
                'admin_alert_email' => ['type' => 'email', 'default' => ''],
                'low_stock_alerts' => ['type' => 'boolean', 'default' => true],
                'expiry_alerts' => ['type' => 'boolean', 'default' => true],
                'abandoned_cart_enabled' => ['type' => 'boolean', 'default' => false],
                'abandoned_cart_hours' => ['type' => 'integer', 'default' => 24, 'rules' => ['integer', 'min:1', 'max:720']],
            ],
            'email' => [
                'footer_text' => ['type' => 'translatable_text', 'default' => ['ja' => '', 'en' => '']],
            ],
            'seo' => [
                'meta_title' => ['type' => 'translatable_string', 'default' => ['ja' => '', 'en' => '']],
                'meta_description' => ['type' => 'translatable_text', 'default' => ['ja' => '', 'en' => '']],
                'og_image' => ['type' => 'image', 'default' => null],
                'google_site_verification' => ['type' => 'string', 'default' => '', 'rules' => ['nullable', 'max:100', 'alpha_dash']],
            ],
            'privacy' => [
                'cookie_notice_enabled' => ['type' => 'boolean', 'default' => true],
                'cookie_notice_text' => ['type' => 'translatable_text', 'default' => ['ja' => '', 'en' => '']],
            ],
            'security' => [
                'login_max_attempts' => ['type' => 'integer', 'default' => 5, 'rules' => ['integer', 'min:3', 'max:20']],
                'password_min_length' => ['type' => 'integer', 'default' => 8, 'rules' => ['integer', 'min:8', 'max:64']],
            ],
            'maintenance' => [
                'enabled' => ['type' => 'boolean', 'default' => false],
                'message' => ['type' => 'translatable_text', 'default' => ['ja' => '', 'en' => '']],
            ],
            'features' => [
                'online_store_enabled' => ['type' => 'boolean', 'default' => true],
                'registration_enabled' => ['type' => 'boolean', 'default' => true],
                'reviews_enabled' => ['type' => 'boolean', 'default' => true],
                'product_questions_enabled' => ['type' => 'boolean', 'default' => true],
                'wishlist_enabled' => ['type' => 'boolean', 'default' => true],
                'coupons_enabled' => ['type' => 'boolean', 'default' => true],
                'chat_enabled' => ['type' => 'boolean', 'default' => false],
                'cash_on_delivery_enabled' => ['type' => 'boolean', 'default' => true],
                'bank_transfer_enabled' => ['type' => 'boolean', 'default' => true],
                'pickup_enabled' => ['type' => 'boolean', 'default' => false],
                'frozen_shipping_enabled' => ['type' => 'boolean', 'default' => true],
                'japanese_enabled' => ['type' => 'boolean', 'default' => true],
                'english_enabled' => ['type' => 'boolean', 'default' => true],
            ],
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function field(string $group, string $key): ?array
    {
        return static::groups()[$group][$key] ?? null;
    }

    public static function hasGroup(string $group): bool
    {
        return array_key_exists($group, static::groups());
    }

    /**
     * Validation rules for submitting a whole settings group from the admin UI.
     *
     * @return array<string, mixed>
     */
    public static function rules(string $group): array
    {
        $rules = [];
        $locales = array_keys(config('shop.locales'));

        foreach (static::groups()[$group] as $key => $field) {
            $custom = $field['rules'] ?? null;

            switch ($field['type']) {
                case 'boolean':
                    $rules[$key] = ['boolean'];
                    break;
                case 'integer':
                    $rules[$key] = $custom ?? ['integer'];
                    array_unshift($rules[$key], 'required');
                    break;
                case 'color':
                    $rules[$key] = ['required', 'regex:/^#[0-9a-fA-F]{6}$/'];
                    break;
                case 'email':
                    $rules[$key] = $custom ?? ['nullable', 'email:rfc', 'max:255'];
                    break;
                case 'url':
                    $rules[$key] = $custom ?? ['nullable', 'url:https,http', 'max:255'];
                    break;
                case 'image':
                    $rules[$key] = ['nullable', 'image', 'mimes:jpg,jpeg,png,webp,ico', 'max:2048'];
                    $rules[$key.'_remove'] = ['nullable', 'boolean'];
                    break;
                case 'select':
                    $rules[$key] = ['required', Rule::in(array_keys($field['options']))];
                    break;
                case 'int_list':
                    $rules[$key] = ['nullable', 'string', 'max:100', 'regex:/^\s*\d+(\s*,\s*\d+)*\s*$/'];
                    break;
                case 'translatable_string':
                case 'translatable_text':
                    $rules[$key] = ['nullable', 'array'];
                    foreach ($locales as $locale) {
                        $rules[$key.'.'.$locale] = ['nullable', 'string', $field['type'] === 'translatable_string' ? 'max:255' : 'max:5000'];
                    }
                    break;
                case 'text':
                    $rules[$key] = $custom ?? ['nullable', 'string', 'max:5000'];
                    break;
                default:
                    $rules[$key] = $custom ?? ['nullable', 'string', 'max:255'];
            }
        }

        return $rules;
    }
}
