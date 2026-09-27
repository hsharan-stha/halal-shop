<x-layouts.admin :title="__('admin.nav.dashboard')">
    <x-ui.page-header :title="__('admin.dashboard.title')" :description="__('admin.dashboard.greeting', ['name' => auth()->user()->name])">
        @isset($filters)
            <x-slot:actions>{{ $filters }}</x-slot:actions>
        @endisset
    </x-ui.page-header>

    @if ($alerts)
        <div class="mb-6 grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
            @foreach ($alerts as $alert)
                <a href="{{ $alert['route'] ?? '#' }}" class="card flex items-center gap-3 border-l-4 p-4 hover:bg-surface-muted {{ ['danger' => 'border-l-danger', 'warning' => 'border-l-warning', 'info' => 'border-l-info'][$alert['tone']] ?? 'border-l-line' }}">
                    <x-icon :name="$alert['icon'] ?? 'alert'" class="{{ ['danger' => 'text-danger', 'warning' => 'text-warning', 'info' => 'text-info'][$alert['tone']] ?? 'text-ink-muted' }}" />
                    <div class="min-w-0 flex-1">
                        <p class="text-sm font-semibold">{{ __('admin.dashboard.alerts.'.$alert['key']) }}</p>
                    </div>
                    <span class="text-xl font-bold tabular-nums">{{ number_format($alert['count']) }}</span>
                </a>
            @endforeach
        </div>
    @endif

    <div class="grid grid-cols-2 gap-3 md:grid-cols-3 xl:grid-cols-4">
        @foreach ($cards as $card)
            @php($tag = ! empty($card['route']) ? 'a' : 'div')
            <{{ $tag }} @if ($tag === 'a') href="{{ $card['route'] }}" @endif class="card p-4 sm:p-5 {{ $tag === 'a' ? 'hover:border-primary' : '' }}">
                <p class="text-xs font-medium text-ink-muted sm:text-sm">{{ __('admin.dashboard.cards.'.$card['key']) }}</p>
                <p class="mt-2 truncate text-xl font-bold tabular-nums sm:text-2xl">{{ is_int($card['value']) ? number_format($card['value']) : $card['value'] }}</p>
            </{{ $tag }}>
        @endforeach
    </div>

    @isset($charts)
        <div class="mt-6">{{ $charts }}</div>
    @endisset
</x-layouts.admin>
