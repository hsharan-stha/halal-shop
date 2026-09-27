<div x-data class="pointer-events-none fixed inset-x-0 bottom-20 z-50 flex flex-col items-center gap-2 px-4 lg:bottom-6 lg:items-end lg:px-6" aria-live="polite" aria-atomic="true">
    <template x-for="toast in $store.toasts.items" :key="toast.id">
        <div x-transition.opacity
             class="pointer-events-auto flex w-full max-w-sm items-start gap-3 rounded-xl border border-line bg-surface px-4 py-3 text-sm shadow-lg"
             :class="{ 'border-l-4 border-l-success': toast.type === 'success', 'border-l-4 border-l-danger': toast.type === 'error', 'border-l-4 border-l-warning': toast.type === 'warning' }"
             role="status">
            <span class="flex-1" x-text="toast.message"></span>
            <button type="button" class="text-ink-muted hover:text-ink" @click="$store.toasts.dismiss(toast.id)" aria-label="{{ __('shop.close') }}">
                <x-icon name="x" class="size-4" />
            </button>
        </div>
    </template>
</div>
