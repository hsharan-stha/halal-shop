<x-layouts.account :title="__('shop.account.nav.profile')">
    <x-ui.card>
        <form method="POST" action="{{ route('account.profile.update') }}" enctype="multipart/form-data" class="grid gap-4 sm:grid-cols-2">
            @csrf
            @method('PUT')
            <x-ui.input name="name" :label="__('shop.fields.name')" :value="$user->name" required autocomplete="name" maxlength="100" />
            <x-ui.input name="email" type="email" :label="__('shop.fields.email')" :value="$user->email" required autocomplete="email" :hint="__('shop.account.email_change_hint')" />
            <x-ui.input name="phone" type="tel" :label="__('shop.fields.phone_optional')" :value="$user->phone" autocomplete="tel" inputmode="tel" />
            <x-ui.input name="date_of_birth" type="date" :label="__('shop.fields.date_of_birth_optional')" :value="$user->profile->date_of_birth?->toDateString()" />
            <x-ui.select name="locale" :label="__('shop.fields.language')" :options="collect(config('shop.locales'))->map(fn ($l) => $l['native'])->all()" :value="$user->locale" required />
            <x-ui.file-upload name="photo" :label="__('shop.fields.profile_photo')" :current="$user->profile->profile_photo_path ? Storage::disk(config('shop.media_disk'))->url($user->profile->profile_photo_path) : null" :removable="true" :hint="__('shop.hints.image_upload')" />
            <div class="sm:col-span-2">
                <x-ui.button>{{ __('shop.save') }}</x-ui.button>
            </div>
        </form>
    </x-ui.card>
</x-layouts.account>
