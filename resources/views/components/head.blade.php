@props(['title' => null, 'description' => null, 'noindex' => false, 'canonical' => null, 'ogImage' => null, 'ogType' => 'website'])

@php
    $siteName = $branding->name();
    $pageTitle = $title ? $title.' | '.$siteName : (settings()->translated('seo.meta_title') ?: $siteName);
    $metaDescription = $description ?: settings()->translated('seo.meta_description');
    $ogImage ??= settings()->mediaUrl(settings('seo.og_image'));
    $themePreference = auth()->user()?->settings?->theme ?? 'system';
@endphp

<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<meta name="csrf-token" content="{{ csrf_token() }}">
<title>{{ $pageTitle }}</title>
@if ($metaDescription)
    <meta name="description" content="{{ Str::limit(strip_tags($metaDescription), 160) }}">
@endif
@if ($noindex)
    <meta name="robots" content="noindex, nofollow">
@else
    <link rel="canonical" href="{{ $canonical ?? url()->current() }}">
@endif
@if ($verification = settings('seo.google_site_verification'))
    <meta name="google-site-verification" content="{{ $verification }}">
@endif

<meta property="og:site_name" content="{{ $siteName }}">
<meta property="og:title" content="{{ $title ?: $siteName }}">
<meta property="og:type" content="{{ $ogType }}">
<meta property="og:url" content="{{ $canonical ?? url()->current() }}">
<meta property="og:locale" content="{{ app()->getLocale() === 'ja' ? 'ja_JP' : 'en_US' }}">
@if ($metaDescription)
    <meta property="og:description" content="{{ Str::limit(strip_tags($metaDescription), 200) }}">
@endif
@if ($ogImage)
    <meta property="og:image" content="{{ $ogImage }}">
    <meta name="twitter:card" content="summary_large_image">
@endif

<meta name="theme-color" content="{{ settings('branding.primary_color') }}">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-title" content="{{ $branding->shortName() }}">
<link rel="manifest" href="{{ route('pwa.manifest') }}">
<link rel="icon" href="{{ $branding->faviconUrl() ?? asset('icons/icon-192.png') }}">
<link rel="apple-touch-icon" href="{{ $branding->faviconUrl() ?? asset('icons/icon-192.png') }}">

@if ($font = $branding->fontFamily())
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link rel="stylesheet" href="https://fonts.bunny.net/css?family={{ urlencode(Str::lower(str_replace(' ', '-', $font))) }}:400,500,700&display=swap">
@endif

<style>:root{ {!! $branding->cssVariables() !!} }</style>

<script>
    (function () {
        var root = document.documentElement;
        root.dataset.darkMode = @js($branding->darkModeEnabled() ? 'enabled' : 'disabled');
        root.dataset.theme = @js($themePreference);
        if (root.dataset.darkMode !== 'enabled') return;
        var pref = localStorage.getItem('theme') || root.dataset.theme;
        if (pref === 'dark' || (pref === 'system' && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
            root.classList.add('dark');
        }
    })();
</script>

@vite(['resources/css/app.css', 'resources/js/app.js'])
@livewireStyles
