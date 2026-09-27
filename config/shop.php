<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Store Fundamentals
    |--------------------------------------------------------------------------
    |
    | Values here are infrastructure-level defaults. Anything a store operator
    | should change at runtime lives in the `settings` table and is managed
    | from the admin panel (see App\Support\Settings\SettingsRegistry).
    |
    */

    'currency' => env('APP_CURRENCY', 'JPY'),

    'locales' => [
        'ja' => ['name' => '日本語', 'native' => '日本語', 'hreflang' => 'ja-JP'],
        'en' => ['name' => 'English', 'native' => 'English', 'hreflang' => 'en'],
    ],

    'media_disk' => env('MEDIA_DISK', 'public'),

    'private_disk' => env('PRIVATE_DISK', 'local'),

    /*
    | Signed URL lifetime (minutes) for private files such as certificates.
    */
    'private_url_ttl' => 10,

    'uploads' => [
        'image_max_kb' => 8192,
        'image_mimes' => ['jpg', 'jpeg', 'png', 'webp'],
        'image_max_dimension' => 8000,
        'document_max_kb' => 10240,
        'document_mimes' => ['pdf', 'jpg', 'jpeg', 'png', 'webp'],
    ],

    /*
    | Responsive image renditions generated for uploaded catalogue images.
    | Width in pixels. Originals are kept but never sent to the storefront.
    */
    'image_sizes' => [
        'thumbnail' => 160,
        'small' => 400,
        'medium' => 800,
        'large' => 1400,
    ],

    'pagination' => [
        'shop' => 24,
        'admin' => 25,
        'api' => 20,
    ],

    'cache_ttl' => [
        'catalog' => 3600,
    ],
];
