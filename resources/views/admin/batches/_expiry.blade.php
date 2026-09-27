{{-- Expiry date with remaining days. Expects $date (date string / Carbon / null). --}}
@if ($date)
    @php
        $expiry = \Illuminate\Support\Carbon::parse(\Illuminate\Support\Carbon::parse($date)->toDateString(), config('app.display_timezone'));
        $days = (int) round(local_today()->diffInDays($expiry, false));
        $warning = (int) (collect(settings('inventory.expiry_warning_days'))->max() ?? 30);
    @endphp
    <span @class(['text-danger font-medium' => $days < 0, 'text-warning font-medium' => $days >= 0 && $days <= $warning])>
        {{ local_date($expiry) }}
        <span class="text-xs">({{ $days < 0 ? __('admin.batches.expired_days_ago', ['days' => abs($days)]) : trans_choice('admin.batches.days_left', $days, ['days' => $days]) }})</span>
    </span>
@else
    <span class="text-ink-muted">—</span>
@endif
