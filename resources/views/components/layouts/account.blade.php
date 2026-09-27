@props(['title'])

@php($items = \App\Support\Navigation::account())

<x-layouts.shop :title="$title" :noindex="true">
    <div class="mx-auto max-w-7xl px-4 py-6 lg:px-6 lg:py-10">
        <div class="grid grid-cols-[minmax(0,1fr)] gap-6 lg:grid-cols-[16rem_minmax(0,1fr)]">
            <aside class="lg:sticky lg:top-24 lg:self-start">
                <div class="card mb-4 flex items-center gap-3 p-4">
                    @if ($photo = auth()->user()->profile?->profile_photo_path)
                        <img src="{{ Storage::disk(config('shop.media_disk'))->url($photo) }}" alt="" class="size-11 rounded-full object-cover">
                    @else
                        <span class="grid size-11 place-items-center rounded-full bg-primary-soft font-bold text-primary">{{ mb_substr(auth()->user()->name, 0, 1) }}</span>
                    @endif
                    <div class="min-w-0">
                        <p class="truncate font-semibold">{{ auth()->user()->name }}</p>
                        <p class="truncate text-xs text-ink-muted">{{ auth()->user()->email }}</p>
                    </div>
                </div>
                <nav aria-label="{{ __('shop.account.nav.label') }}" class="scrollbar-none -mx-4 overflow-x-auto px-4 lg:mx-0 lg:overflow-visible lg:px-0">
                    <ul class="flex gap-2 lg:card lg:flex-col lg:gap-0 lg:p-2">
                        @foreach ($items as $item)
                            @php($active = request()->routeIs($item['active']))
                            <li class="shrink-0">
                                <a href="{{ route($item['route']) }}" @class([
                                    'flex min-h-10 items-center gap-2 rounded-full border px-3.5 text-sm whitespace-nowrap lg:rounded-lg lg:border-0',
                                    'border-primary bg-primary-soft font-semibold text-primary' => $active,
                                    'border-line bg-surface text-ink-muted hover:text-ink lg:bg-transparent lg:hover:bg-surface-muted' => ! $active,
                                ]) @if ($active) aria-current="page" @endif>
                                    <x-icon :name="$item['icon']" class="hidden size-4 lg:block" />
                                    {{ __($item['label']) }}
                                </a>
                            </li>
                        @endforeach
                        <li class="shrink-0 lg:mt-1 lg:border-t lg:border-line lg:pt-1">
                            <form method="POST" action="{{ route('logout') }}">
                                @csrf
                                <button type="submit" class="flex min-h-10 w-full items-center gap-2 rounded-full border border-line bg-surface px-3.5 text-sm whitespace-nowrap text-danger lg:rounded-lg lg:border-0 lg:bg-transparent lg:hover:bg-surface-muted">
                                    <x-icon name="logout" class="hidden size-4 lg:block" />{{ __('shop.auth.logout') }}
                                </button>
                            </form>
                        </li>
                    </ul>
                </nav>
            </aside>

            <div class="min-w-0">
                <h1 class="mb-5 text-2xl font-bold">{{ $title }}</h1>
                {{ $slot }}
            </div>
        </div>
    </div>
</x-layouts.shop>
