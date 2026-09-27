<x-layouts.admin :title="__('admin.nav.health')">
    <x-ui.page-header :title="__('admin.health.title')" :description="__('admin.health.description')" />

    <div class="grid gap-3 md:grid-cols-2 xl:grid-cols-3">
        @foreach ($checks as $check)
            <div class="card flex items-start gap-3 p-4">
                <span @class(['grid size-10 shrink-0 place-items-center rounded-xl', 'bg-success-soft text-success' => $check['ok'], 'bg-danger-soft text-danger' => ! $check['ok']])>
                    <x-icon :name="$check['ok'] ? 'check-circle' : 'alert'" />
                </span>
                <div class="min-w-0">
                    <p class="font-semibold">{{ __('admin.health.checks.'.$check['key']) }}</p>
                    <p class="text-sm break-words text-ink-muted">{{ $check['detail'] }}</p>
                </div>
            </div>
        @endforeach
    </div>
</x-layouts.admin>
