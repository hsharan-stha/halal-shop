@php($daysLeft = $certification->daysUntilExpiry())
<span @class([
    'text-danger font-medium' => $daysLeft < 0,
    'text-warning font-medium' => $daysLeft >= 0 && $daysLeft <= 30,
])>{{ local_date($certification->expires_at) }}</span>
<span class="text-xs text-ink-muted">
    ({{ $daysLeft < 0 ? __('admin.halal.expired') : trans_choice('admin.halal.days_left', $daysLeft, ['days' => $daysLeft]) }})
</span>
