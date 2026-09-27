{{--
    Responsive data table: a real table from md up, stacked cards below.
    Provide `head` (th cells), the default slot (tr rows) and `mobile` (card list).
--}}
<div {{ $attributes->merge(['class' => 'card overflow-hidden']) }}>
    <div class="hidden overflow-x-auto md:block">
        <table class="data-table">
            <thead><tr>{{ $head }}</tr></thead>
            <tbody>{{ $slot }}</tbody>
        </table>
    </div>
    <div class="divide-y divide-line md:hidden">{{ $mobile }}</div>
</div>
