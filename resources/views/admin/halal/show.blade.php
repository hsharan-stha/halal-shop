<x-layouts.admin :title="$certification->certifying_body">
    <x-ui.page-header :title="$certification->certifying_body" :description="'#'.$certification->certificate_number" :back="route('admin.halal-certifications.index')">
        <x-slot:actions>
            <x-ui.badge :color="$certification->status->color()">{{ $certification->status->label() }}</x-ui.badge>
            <x-admin.owner-badge :row="$certification" />
            @can('halal_certificates.manage')
                @if ($certification->isManageableByCurrentUser())
                    <x-ui.button variant="secondary" size="sm" icon="pencil" :href="route('admin.halal-certifications.edit', $certification)">{{ __('admin.edit') }}</x-ui.button>
                    <x-ui.confirm-form :action="route('admin.halal-certifications.destroy', $certification)" method="DELETE" :message="__('admin.halal.confirm_delete')">
                        <x-ui.button variant="ghost" size="sm" icon="trash" class="text-danger">{{ __('admin.delete') }}</x-ui.button>
                    </x-ui.confirm-form>
                @endif
            @endcan
        </x-slot:actions>
    </x-ui.page-header>

    <div class="grid gap-6 lg:grid-cols-3">
        <div class="min-w-0 space-y-6 lg:col-span-2">
            <x-ui.card :title="__('admin.halal.certificate_details')">
                <dl class="grid gap-4 text-sm sm:grid-cols-2">
                    <div><dt class="text-ink-muted">{{ __('admin.halal.certifying_body') }}</dt><dd class="font-medium">{{ $certification->certifying_body }}</dd></div>
                    <div><dt class="text-ink-muted">{{ __('admin.halal.certificate_number') }}</dt><dd class="font-mono">{{ $certification->certificate_number }}</dd></div>
                    <div><dt class="text-ink-muted">{{ __('admin.halal.issued_at') }}</dt><dd>{{ $certification->issued_at ? local_date($certification->issued_at) : '—' }}</dd></div>
                    <div><dt class="text-ink-muted">{{ __('admin.halal.expires_at') }}</dt><dd>@include('admin.halal._expiry')</dd></div>
                    <div><dt class="text-ink-muted">{{ __('admin.halal.brand') }}</dt><dd>{{ $certification->brand?->localizedName() ?? '—' }}</dd></div>
                    <div><dt class="text-ink-muted">{{ __('admin.halal.scope') }}</dt><dd>{{ $certification->scope ?: '—' }}</dd></div>
                    @if ($certification->notes)
                        <div class="sm:col-span-2"><dt class="text-ink-muted">{{ __('admin.halal.internal_notes') }}</dt><dd class="whitespace-pre-line">{{ $certification->notes }}</dd></div>
                    @endif
                </dl>
            </x-ui.card>

            <x-ui.card :title="__('admin.halal.covered_products')" padding="p-0">
                @if ($certification->products->isEmpty())
                    <p class="p-4 text-sm text-ink-muted sm:px-6">{{ __('admin.halal.no_products') }}</p>
                @else
                    <ul class="divide-y divide-line">
                        @foreach ($certification->products as $product)
                            <li class="flex items-center justify-between gap-3 px-4 py-3 sm:px-6">
                                <div class="min-w-0">
                                    <p class="font-medium">{{ $product->localizedName() }}</p>
                                    <p class="text-xs text-ink-muted"><span class="font-mono">{{ $product->sku }}</span> · {{ $product->category?->localizedName() }}</p>
                                </div>
                                <div class="flex items-center gap-2">
                                    <x-ui.badge :color="$product->halal_status->color()">{{ $product->halal_status->label() }}</x-ui.badge>
                                    @can('products.update')
                                        <x-ui.button variant="ghost" size="sm" icon="pencil" :href="route('admin.products.edit', $product)" :aria-label="__('admin.edit')" />
                                    @endcan
                                </div>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </x-ui.card>
        </div>

        <div class="min-w-0 space-y-6">
            <x-ui.card :title="__('admin.halal.certificate_file')">
                @if ($fileUrl)
                    <p class="mb-3 text-sm break-all">{{ $certification->file_name }}</p>
                    <x-ui.button variant="secondary" :href="$fileUrl" icon="document" target="_blank" rel="noopener">{{ __('admin.halal.open_file') }}</x-ui.button>
                    <p class="form-hint mt-2">{{ __('admin.halal.signed_link_hint', ['minutes' => config('shop.private_url_ttl')]) }}</p>
                @else
                    <p class="text-sm text-ink-muted">{{ __('admin.halal.no_file') }}</p>
                @endif
            </x-ui.card>

            <x-ui.card :title="__('admin.halal.verification')">
                <div class="space-y-4 text-sm">
                    @if ($certification->verified_at)
                        <p>{{ __($certification->status === \App\Enums\CertificationStatus::Rejected ? 'admin.halal.rejected_by' : 'admin.halal.verified_by', ['name' => $certification->verifier?->name ?? '—', 'date' => local_date($certification->verified_at, true)]) }}</p>
                    @else
                        <p class="text-ink-muted">{{ __('admin.halal.awaiting_verification') }}</p>
                    @endif
                    @if ($certification->rejection_reason)
                        <x-ui.alert type="danger">{{ $certification->rejection_reason }}</x-ui.alert>
                    @endif

                    @can('halal_certificates.verify')
                        @if ($certification->status !== \App\Enums\CertificationStatus::Verified)
                            <x-ui.confirm-form :action="route('admin.halal-certifications.verify', $certification)" :message="__('admin.halal.confirm_verify')">
                                <p class="form-hint mb-2">{{ __('admin.halal.verify_hint') }}</p>
                                <x-ui.button icon="shield-check" class="w-full" :disabled="! $certification->hasFile() || $certification->isExpired()">{{ __('admin.halal.verify') }}</x-ui.button>
                            </x-ui.confirm-form>
                        @endif
                        @if ($certification->status !== \App\Enums\CertificationStatus::Rejected)
                            <form method="POST" action="{{ route('admin.halal-certifications.reject', $certification) }}" class="space-y-2 border-t border-line pt-4">
                                @csrf
                                <x-ui.textarea name="reason" :label="__('admin.halal.reject_reason')" :rows="2" required maxlength="500" />
                                <x-ui.button variant="danger" class="w-full">{{ __('admin.halal.reject') }}</x-ui.button>
                            </form>
                        @endif
                    @else
                        <p class="text-xs text-ink-muted">{{ __('admin.halal.verify_permission_required') }}</p>
                    @endcan
                </div>
            </x-ui.card>
        </div>
    </div>
</x-layouts.admin>
