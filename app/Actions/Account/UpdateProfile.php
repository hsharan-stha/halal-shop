<?php

namespace App\Actions\Account;

use App\Models\User;
use App\Rules\JapanesePhone;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class UpdateProfile
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function handle(User $user, array $data, ?UploadedFile $photo = null): User
    {
        return DB::transaction(function () use ($user, $data, $photo): User {
            $emailChanged = $data['email'] !== $user->email;

            $user->fill([
                'name' => $data['name'],
                'email' => $data['email'],
                'phone' => ! empty($data['phone']) ? JapanesePhone::normalize($data['phone']) : null,
                'locale' => $data['locale'] ?? $user->locale,
            ]);

            if ($emailChanged) {
                $user->email_verified_at = null;
            }

            $user->save();

            $profile = $user->profile;
            $profile->user_id = $user->id;
            $profile->date_of_birth = $data['date_of_birth'] ?? null;

            $disk = Storage::disk(config('shop.media_disk'));

            if ($photo || ! empty($data['remove_photo'])) {
                if ($profile->profile_photo_path) {
                    $disk->delete($profile->profile_photo_path);
                }

                $profile->profile_photo_path = $photo ? $photo->store('users', config('shop.media_disk')) : null;
            }

            $profile->save();

            if ($emailChanged) {
                $user->sendEmailVerificationNotification();
            }

            return $user;
        });
    }
}
