<x-layouts.admin :title="__('admin.nav.audit_logs')">
    <x-ui.page-header :title="__('admin.audit.title')" :description="__('admin.audit.description')" />

    <form method="GET" class="card mb-4 grid gap-3 p-4 sm:grid-cols-4 sm:items-end" x-data="{ open: window.innerWidth >= 640 }">
        <button type="button" class="btn btn-secondary sm:hidden" @click="open = !open"><x-icon name="filter" class="size-4" />{{ __('admin.filters') }}</button>
        <div x-show="open" class="contents">
            <x-ui.input name="action" :label="__('admin.audit.action')" :value="request('action')" placeholder="product." />
            <x-ui.input name="from" type="date" :label="__('admin.from')" :value="request('from')" />
            <x-ui.input name="to" type="date" :label="__('admin.to')" :value="request('to')" />
            <x-ui.button variant="secondary">{{ __('admin.apply') }}</x-ui.button>
        </div>
    </form>

    <x-ui.table>
        <x-slot:head>
            <th>{{ __('admin.audit.when') }}</th>
            <th>{{ __('admin.audit.actor') }}</th>
            <th>{{ __('admin.audit.action') }}</th>
            <th>{{ __('admin.audit.entity') }}</th>
            <th>{{ __('admin.audit.changes') }}</th>
            <th>IP</th>
        </x-slot:head>
        @forelse ($logs as $log)
            <tr class="align-top">
                <td class="text-xs whitespace-nowrap text-ink-muted">{{ local_date($log->created_at, true) }}</td>
                <td class="text-sm">{{ $log->user?->name ?? __('admin.audit.system') }}</td>
                <td><code class="text-xs">{{ $log->action }}</code></td>
                <td class="text-xs text-ink-muted">{{ $log->auditable_type ? class_basename($log->auditable_type).' #'.$log->auditable_id : '—' }}</td>
                <td class="max-w-md">@include('admin.audit-logs._changes')</td>
                <td class="text-xs text-ink-muted">{{ $log->ip_address }}</td>
            </tr>
        @empty
            <tr><td colspan="6"><x-ui.empty-state icon="clipboard" :title="__('admin.audit.empty')" /></td></tr>
        @endforelse
        <x-slot:mobile>
            @forelse ($logs as $log)
                <div class="space-y-1 p-4 text-sm">
                    <div class="flex justify-between gap-2"><code class="text-xs">{{ $log->action }}</code><span class="text-xs text-ink-muted">{{ local_date($log->created_at, true) }}</span></div>
                    <p class="text-xs text-ink-muted">{{ $log->user?->name ?? __('admin.audit.system') }} @if ($log->auditable_type) · {{ class_basename($log->auditable_type) }} #{{ $log->auditable_id }} @endif</p>
                    @include('admin.audit-logs._changes')
                </div>
            @empty
                <x-ui.empty-state icon="clipboard" :title="__('admin.audit.empty')" />
            @endforelse
        </x-slot:mobile>
    </x-ui.table>

    <div class="mt-4">{{ $logs->links() }}</div>
</x-layouts.admin>
