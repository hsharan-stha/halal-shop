{{-- Form that requires explicit confirmation before submitting (destructive actions). --}}
@props(['action', 'method' => 'POST', 'message' => null])

<form method="POST" action="{{ $action }}" x-data="confirmSubmit(@js($message ?? __('shop.confirm_action')))" @submit="submit($event)" {{ $attributes }}>
    @csrf
    @if (strtoupper($method) !== 'POST')
        @method($method)
    @endif
    {{ $slot }}
</form>
