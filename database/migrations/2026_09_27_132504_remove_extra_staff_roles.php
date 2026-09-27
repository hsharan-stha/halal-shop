<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Staff roles other than super admin and halal shop are no longer used.
     *
     * @var list<string>
     */
    private array $removed = [
        'admin',
        'order_manager',
        'product_manager',
        'inventory_manager',
        'content_manager',
        'support_agent',
    ];

    /**
     * Demo logins created for the removed roles.
     *
     * @var list<string>
     */
    private array $removedEmails = [
        'admin@example.com',
        'orders@example.com',
        'products@example.com',
        'inventory@example.com',
        'content@example.com',
        'support@example.com',
    ];

    public function up(): void
    {
        $roleIds = DB::table('roles')->whereIn('slug', $this->removed)->pluck('id');
        $affectedUserIds = $roleIds->isEmpty()
            ? collect()
            : DB::table('user_roles')->whereIn('role_id', $roleIds)->pluck('user_id')->unique();

        if ($roleIds->isNotEmpty()) {
            DB::table('roles')->whereIn('id', $roleIds)->delete();
        }

        $emailUserIds = DB::table('users')->whereIn('email', $this->removedEmails)->pluck('id');
        $candidateIds = $affectedUserIds->merge($emailUserIds)->unique()->values();

        if ($candidateIds->isEmpty()) {
            return;
        }

        $keptRoleIds = DB::table('roles')->whereIn('slug', ['super_admin', 'halal_shop', 'customer'])->pluck('id');
        $keptUserIds = $keptRoleIds->isEmpty()
            ? collect()
            : DB::table('user_roles')->whereIn('role_id', $keptRoleIds)->whereIn('user_id', $candidateIds)->pluck('user_id');

        $removeUserIds = $candidateIds->diff($keptUserIds)->values();

        if ($removeUserIds->isEmpty()) {
            return;
        }

        DB::table('sessions')->whereIn('user_id', $removeUserIds)->delete();

        if (Schema::hasTable('personal_access_tokens')) {
            DB::table('personal_access_tokens')
                ->where('tokenable_type', 'App\\Models\\User')
                ->whereIn('tokenable_id', $removeUserIds)
                ->delete();
        }

        DB::table('users')->whereIn('id', $removeUserIds)->whereNull('deleted_at')->update([
            'is_staff' => false,
            'deleted_at' => now(),
        ]);
    }

    public function down(): void
    {
        $now = now();

        foreach ($this->removed as $slug) {
            DB::table('roles')->insertOrIgnore([
                'slug' => $slug,
                'name' => $slug,
                'is_system' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }
};
