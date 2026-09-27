<?php

namespace App\Actions\Account;

use App\Enums\UserStatus;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Anonymises and soft-deletes an account. Order records are retained for
 * accounting obligations but keep only their own address snapshots.
 */
class DeleteAccount
{
    public function __construct(private AuditLogger $auditLogger) {}

    public function handle(User $user, ?User $actor = null): void
    {
        DB::transaction(function () use ($user, $actor): void {
            if ($path = $user->profile->profile_photo_path) {
                Storage::disk(config('shop.media_disk'))->delete($path);
            }

            $this->revokeAccess($user);

            foreach (['addresses', 'wishlistItems', 'cart'] as $relation) {
                if (method_exists($user, $relation)) {
                    $user->{$relation}()->delete();
                }
            }

            $user->profile()->delete();
            $user->settings()->delete();

            $user->forceFill([
                'name' => __('shop.account.deleted_user'),
                'email' => 'deleted+'.$user->ulid.'@invalid.local',
                'phone' => null,
                'password' => Str::random(64),
                'status' => UserStatus::Deactivated,
                'remember_token' => null,
            ])->save();

            $user->delete();

            $this->auditLogger->log('user.deleted', $user, actorId: $actor?->id ?? $user->id);
        });
    }

    public function revokeAccess(User $user): void
    {
        $user->tokens()->delete();
        DB::table('sessions')->where('user_id', $user->id)->delete();
    }
}
