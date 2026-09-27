<?php

namespace Database\Seeders;

use App\Enums\RoleSlug;
use App\Models\Permission;
use App\Models\Role;
use App\Support\PermissionCatalog;
use Illuminate\Database\Seeder;

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
    }
}
