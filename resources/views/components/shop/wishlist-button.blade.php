@props(['product', 'wished' => false])

<form method="POST" action="{{ route($wished ? 'wishlist.destroy' : 'wishlist.store', $product->slug) }}">
    @csrf
    @if ($wished)
        @method('DELETE')
    @endif
    <button type="submit" class="grid size-10 place-items-center rounded-full bg-surface/90 text-ink shadow-sm hover:text-danger" aria-pressed="{{ $wished ? 'true' : 'false' }}" aria-label="{{ $wished ? __('shop.wishlist.remove') : __('shop.wishlist.add') }}">
        <x-icon name="heart" :solid="$wished" @class(['size-5', 'text-danger' => $wished]) />
    </button>
</form>
