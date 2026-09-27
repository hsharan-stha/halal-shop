@if ($paginator->hasPages())
    <nav role="navigation" aria-label="{{ __('pagination.label') }}" class="flex items-center justify-between gap-2">
        @if ($paginator->onFirstPage())
            <span class="btn btn-secondary btn-sm opacity-50" aria-disabled="true"><x-icon name="chevron-left" class="size-4" />{{ __('pagination.previous') }}</span>
        @else
            <a href="{{ $paginator->previousPageUrl() }}" rel="prev" class="btn btn-secondary btn-sm"><x-icon name="chevron-left" class="size-4" />{{ __('pagination.previous') }}</a>
        @endif
        @if ($paginator->hasMorePages())
            <a href="{{ $paginator->nextPageUrl() }}" rel="next" class="btn btn-secondary btn-sm">{{ __('pagination.next') }}<x-icon name="chevron-right" class="size-4" /></a>
        @else
            <span class="btn btn-secondary btn-sm opacity-50" aria-disabled="true">{{ __('pagination.next') }}<x-icon name="chevron-right" class="size-4" /></span>
        @endif
    </nav>
@endif
