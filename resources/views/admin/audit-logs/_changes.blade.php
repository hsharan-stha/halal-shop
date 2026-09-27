@if ($log->new_values || $log->old_values)
    <details class="text-xs">
        <summary class="cursor-pointer text-primary">{{ __('admin.audit.view_changes') }}</summary>
        <div class="mt-2 space-y-1 break-all">
            @foreach (array_unique(array_merge(array_keys($log->old_values ?? []), array_keys($log->new_values ?? []))) as $field)
                <div>
                    <span class="font-semibold">{{ $field }}:</span>
                    <span class="text-danger line-through">{{ Str::limit(is_scalar($log->old_values[$field] ?? null) ? (string) $log->old_values[$field] : json_encode($log->old_values[$field] ?? null, JSON_UNESCAPED_UNICODE), 120) }}</span>
                    →
                    <span class="text-success">{{ Str::limit(is_scalar($log->new_values[$field] ?? null) ? (string) $log->new_values[$field] : json_encode($log->new_values[$field] ?? null, JSON_UNESCAPED_UNICODE), 120) }}</span>
                </div>
            @endforeach
        </div>
    </details>
@else
    <span class="text-xs text-ink-muted">—</span>
@endif
