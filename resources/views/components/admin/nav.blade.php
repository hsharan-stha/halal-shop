@props(['sections', 'collapsible' => false])

<nav class="flex-1 overflow-y-auto px-3 py-4" aria-label="{{ __('admin.nav.menu') }}">
    @foreach ($sections as $section)
        <div class="mb-5">
            <p class="mb-1 px-3 text-[11px] font-semibold tracking-wider text-ink-muted uppercase" @if ($collapsible) x-show="!collapsed" @endif>{{ __($section['heading']) }}</p>
            <ul class="space-y-0.5">
                @foreach ($section['items'] as $item)
                    @php($active = request()->routeIs($item['active']))
                    <li>
                        <a href="{{ route($item['route']) }}" title="{{ __($item['label']) }}"
                           @class(['flex min-h-10 items-center gap-3 rounded-lg px-3 text-sm font-medium', 'bg-primary-soft text-primary' => $active, 'text-ink-muted hover:bg-surface-muted hover:text-ink' => ! $active])
                           @if ($active) aria-current="page" @endif>
                            <x-icon :name="$item['icon']" />
                            <span class="truncate" @if ($collapsible) x-show="!collapsed" @endif>{{ __($item['label']) }}</span>
                        </a>
                    </li>
                @endforeach
            </ul>
        </div>
    @endforeach
</nav>
