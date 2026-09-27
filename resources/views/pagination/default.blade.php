@if ($paginator->hasPages())
    <nav role="navigation" aria-label="{{ __('pagination.label') }}" class="flex items-center justify-between gap-2">
        <p class="hidden text-sm text-ink-muted sm:block">
            {{ __('pagination.showing', ['first' => $paginator->firstItem() ?? 0, 'last' => $paginator->lastItem() ?? 0, 'total' => $paginator->total()]) }}
        </p>
        <ul class="flex flex-wrap items-center gap-1">
            <li>
                @if ($paginator->onFirstPage())
                    <span class="btn btn-secondary btn-sm opacity-50" aria-disabled="true"><x-icon name="chevron-left" class="size-4" /><span class="sr-only">{{ __('pagination.previous') }}</span></span>
                @else
                    <a href="{{ $paginator->previousPageUrl() }}" rel="prev" class="btn btn-secondary btn-sm"><x-icon name="chevron-left" class="size-4" /><span class="sr-only">{{ __('pagination.previous') }}</span></a>
                @endif
            </li>
            @foreach ($elements as $element)
                @if (is_string($element))
                    <li class="hidden sm:block"><span class="px-2 text-ink-muted">{{ $element }}</span></li>
                @endif
                @if (is_array($element))
                    @foreach ($element as $page => $url)
                        <li @class(['hidden sm:block' => abs($page - $paginator->currentPage()) > 1])>
                            @if ($page == $paginator->currentPage())
                                <span class="btn btn-primary btn-sm min-w-9" aria-current="page">{{ $page }}</span>
                            @else
                                <a href="{{ $url }}" class="btn btn-secondary btn-sm min-w-9">{{ $page }}</a>
                            @endif
                        </li>
                    @endforeach
                @endif
            @endforeach
            <li>
                @if ($paginator->hasMorePages())
                    <a href="{{ $paginator->nextPageUrl() }}" rel="next" class="btn btn-secondary btn-sm"><span class="sr-only">{{ __('pagination.next') }}</span><x-icon name="chevron-right" class="size-4" /></a>
                @else
                    <span class="btn btn-secondary btn-sm opacity-50" aria-disabled="true"><span class="sr-only">{{ __('pagination.next') }}</span><x-icon name="chevron-right" class="size-4" /></span>
                @endif
            </li>
        </ul>
    </nav>
@endif
