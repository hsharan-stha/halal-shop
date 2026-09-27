@props(['title' => null])

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <x-head :title="$title" :noindex="true" />
</head>
<body class="flex min-h-dvh flex-col items-center px-4 py-10">
    <main class="my-auto w-full max-w-md">
        <div class="mb-6 flex flex-col items-center gap-2 text-center">
            @if ($logo = $branding->logoUrl())
                <img src="{{ $logo }}" alt="{{ $branding->name() }}" class="h-12 w-auto">
            @else
                <span class="grid size-12 place-items-center rounded-2xl bg-primary text-lg font-bold text-on-primary">{{ mb_substr($branding->shortName(), 0, 1) }}</span>
            @endif
            <p class="font-bold">{{ $branding->name() }}</p>
        </div>
        <x-flash />
        <div class="card mt-4 p-6 sm:p-8">{{ $slot }}</div>
        <div class="mt-4 flex justify-center"><x-locale-switcher /></div>
    </main>
    @livewireScripts
</body>
</html>
