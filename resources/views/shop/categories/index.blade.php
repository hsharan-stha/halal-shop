<x-layouts.shop :title="__('shop.categories.title')" :description="__('shop.categories.description')">
    <div class="mx-auto max-w-7xl px-4 py-6 lg:px-6">
        <h1 class="text-2xl font-bold tracking-tight sm:text-3xl">{{ __('shop.categories.title') }}</h1>
        <p class="mt-1 text-sm text-ink-muted">{{ __('shop.categories.description') }}</p>

        @if ($categories->isEmpty())
            <x-ui.empty-state class="mt-8" icon="squares" :title="__('shop.categories.empty')" />
        @else
            <ul class="mt-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($categories as $category)
                    <li class="card p-4">
                        <a href="{{ route('categories.show', $category) }}" class="flex items-center justify-between gap-3">
                            <span class="flex items-center gap-2 font-semibold">
                                <x-icon :name="$category->icon ?: 'squares'" class="size-5 text-primary" />
                                {{ $category->localizedName() }}
                            </span>
                            <span class="text-sm text-ink-muted tabular-nums">{{ trans_choice('shop.categories.products', $category->products_count, ['count' => number_format($category->products_count)]) }}</span>
                        </a>
                        @if ($category->children->isNotEmpty())
                            <ul class="mt-3 flex flex-wrap gap-2">
                                @foreach ($category->children as $child)
                                    <li><a href="{{ route('categories.show', $child) }}" class="btn btn-secondary btn-sm">{{ $child->localizedName() }}</a></li>
                                @endforeach
                            </ul>
                        @endif
                    </li>
                @endforeach
            </ul>
        @endif
    </div>
</x-layouts.shop>
