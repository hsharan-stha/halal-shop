<?php

namespace Database\Seeders;

use App\Enums\RoleSlug;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Support\PermissionCatalog;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Idempotently syncs the permission catalogue and system roles from
 * config/permissions.php. Safe to re-run in production after deployments.
 */
class RolePermissionSeeder extends Seeder
{
    public function run(): void
    {
        foreach (PermissionCatalog::grouped() as $group => $permissions) {
            foreach ($permissions as $slug) {
                Permission::query()->updateOrCreate(['slug' => $slug], ['group' => $group]);
            }
        }

        Permission::query()->whereNotIn('slug', PermissionCatalog::all())->delete();

        foreach (config('permissions.roles') as $slug => $patterns) {
            $role = Role::query()->firstOrCreate(
                ['slug' => $slug],
                ['name' => RoleSlug::tryFrom($slug)?->name ?? $slug, 'is_system' => true],
            );

            if ($role->wasRecentlyCreated || $slug === RoleSlug::SuperAdmin->value) {
                $role->permissions()->sync(
                    Permission::query()->whereIn('slug', PermissionCatalog::expand($patterns))->pluck('id'),
                );
            }
        }

        $removed = Role::query()->whereNotIn('slug', array_keys(config('permissions.roles')))->get();

        if ($removed->isEmpty()) {
            return;
        }

        $userIds = DB::table('user_roles')->whereIn('role_id', $removed->pluck('id'))->pluck('user_id')->unique();
        $removed->each->delete();

        $stillAssigned = DB::table('user_roles')->whereIn('user_id', $userIds)->pluck('user_id');
        $orphans = $userIds->diff($stillAssigned);

        if ($orphans->isEmpty()) {
            return;
        }

        DB::table('sessions')->whereIn('user_id', $orphans)->delete();

        if (Schema::hasTable('personal_access_tokens')) {
            DB::table('personal_access_tokens')
                ->where('tokenable_type', User::class)
                ->whereIn('tokenable_id', $orphans)
                ->delete();
        }

        User::query()->whereIn('id', $orphans)->get()->each(function (User $user): void {
            $user->forceFill(['is_staff' => false])->save();
            $user->delete();
        });
    }
}
