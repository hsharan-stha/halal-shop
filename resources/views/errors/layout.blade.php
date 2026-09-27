{{-- Minimal, dependency-free error layout: must render even when the app is degraded. --}}
@php
    try {
        $siteName = app(\App\Services\BrandingService::class)->name();
        $primary = settings('branding.primary_color', '#0f7a4f');
    } catch (\Throwable) {
        $siteName = config('app.name');
        $primary = '#0f7a4f';
    }
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>@yield('title') | {{ $siteName }}</title>
    <style>
        body { margin: 0; min-height: 100vh; display: grid; place-items: center; padding: 24px; font-family: 'Hiragino Sans', 'Noto Sans JP', system-ui, sans-serif; background: #f7f7f5; color: #1c1f1d; text-align: center; }
        main { max-width: 480px; }
        .code { font-size: 14px; font-weight: 700; color: {{ preg_match('/^#[0-9a-fA-F]{6}$/', $primary) ? $primary : '#0f7a4f' }}; letter-spacing: .1em; }
        h1 { font-size: 24px; margin: 8px 0 12px; }
        p { color: #5c635f; line-height: 1.7; }
        a { display: inline-block; margin-top: 16px; min-height: 44px; line-height: 44px; padding: 0 20px; border-radius: 12px; background: {{ preg_match('/^#[0-9a-fA-F]{6}$/', $primary) ? $primary : '#0f7a4f' }}; color: #fff; text-decoration: none; font-weight: 600; }
    </style>
</head>
<body>
    <main>
        <p class="code">@yield('code')</p>
        <h1>@yield('title')</h1>
        <p>@yield('message')</p>
        @hasSection('hide_home')
        @else
            <a href="{{ url('/') }}">{{ __('shop.errors.home') }}</a>
        @endif
    </main>
</body>
</html>
