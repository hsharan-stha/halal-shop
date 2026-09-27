@php($thumb = $product->primaryImage())
@if ($thumb)
    <img src="{{ $thumb->url('thumbnail') }}" alt="" width="48" height="48" class="size-12 shrink-0 rounded-lg border border-line bg-white object-cover" loading="lazy">
@else
    <span class="grid size-12 shrink-0 place-items-center rounded-lg bg-surface-muted text-ink-muted"><x-icon name="photo" class="size-5" /></span>
@endif
