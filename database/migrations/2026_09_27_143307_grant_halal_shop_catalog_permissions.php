<?php

use App\Enums\RoleSlug;
use App\Models\Permission;
use App\Models\Role;
use App\Support\PermissionCatalog;
use Illuminate\Database\Migrations\Migration;

/**
 * A halal shop now runs its own catalogue, stock and purchasing, while order
 * handling stays with the super admin. The seeder only syncs permissions for
 * newly created roles, so the live halal_shop role is resynced here.
 */
return new class extends Migration
{
    public function up(): void
    {
        $role = Role::query()->where('slug', RoleSlug::HalalShop->value)->first();

        if ($role === null) {
            return;
        }

        $role->permissions()->sync(
            Permission::query()
                ->whereIn('slug', PermissionCatalog::expand(config('permissions.roles.'.RoleSlug::HalalShop->value, [])))
                ->pluck('id'),
        );
    }

    public function down(): void
    {
        //
    }
};
