@php
    $messages = array_filter([
        'success' => session('success'),
        'danger' => session('error'),
        'info' => session('status'),
        'warning' => session('warning'),
    ]);
@endphp

@if ($messages)
    <div class="mx-auto max-w-7xl space-y-2 px-4 pt-4 lg:px-6">
        @foreach ($messages as $type => $message)
            <x-ui.alert :type="$type" dismissible>{{ $message }}</x-ui.alert>
        @endforeach
    </div>
@endif
