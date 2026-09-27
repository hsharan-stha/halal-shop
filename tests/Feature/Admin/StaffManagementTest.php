<?php

namespace Tests\Feature\Admin;

use App\Enums\RoleSlug;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StaffManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_super_admin_can_create_staff_member(): void
    {
        $this->actingAs($this->staff(RoleSlug::SuperAdmin))->post(route('admin.staff.store'), [
            'name' => 'New Packer',
            'email' => 'packer@example.com',
            'password' => 'secret123',
            'password_confirmation' => 'secret123',
            'roles' => ['halal_shop'],
        ])->assertRedirect(route('admin.staff.index'));

        $member = User::query()->where('email', 'packer@example.com')->firstOrFail();
        $this->assertTrue($member->isStaff());
        $this->assertTrue($member->hasRole(RoleSlug::HalalShop));
    }

    public function test_halal_shop_cannot_grant_super_admin_role(): void
    {
        $shopUser = $this->staff(RoleSlug::HalalShop);
        $shopUser->roles()->first()->permissions()->syncWithoutDetaching(
            Permission::query()->where('slug', 'staff.manage')->pluck('id'),
        );

        $this->actingAs($shopUser->fresh())->post(route('admin.staff.store'), [
            'name' => 'Escalation',
            'email' => 'escalate@example.com',
            'password' => 'secret123',
            'password_confirmation' => 'secret123',
            'roles' => ['super_admin'],
        ])->assertSessionHasErrors('roles.0');

        $this->assertDatabaseMissing('users', ['email' => 'escalate@example.com']);
    }

    public function test_admin_without_staff_manage_permission_cannot_create_staff(): void
    {
        $this->actingAs($this->staff(RoleSlug::HalalShop))->post(route('admin.staff.store'), [
            'name' => 'X', 'email' => 'x@example.com', 'password' => 'secret123', 'password_confirmation' => 'secret123', 'roles' => ['halal_shop'],
        ])->assertForbidden();
    }

    public function test_suspending_staff_revokes_tokens(): void
    {
        $member = $this->staff(RoleSlug::HalalShop);
        $member->createToken('device');

        $this->actingAs($this->staff(RoleSlug::SuperAdmin))
            ->post(route('admin.staff.toggle-status', $member))
            ->assertRedirect();

        $this->assertFalse($member->fresh()->isActive());
        $this->assertSame(0, $member->tokens()->count());
    }

    public function test_role_permissions_can_be_updated_but_not_for_super_admin(): void
    {
        $superAdmin = $this->staff(RoleSlug::SuperAdmin);
        $role = Role::query()->where('slug', 'halal_shop')->firstOrFail();

        $this->actingAs($superAdmin)->put(route('admin.roles.update', $role), [
            'permissions' => ['support.view', 'orders.view', 'not.a.permission'],
        ])->assertSessionHasErrors('permissions.2');

        $this->actingAs($superAdmin)->put(route('admin.roles.update', $role), [
            'permissions' => ['support.view', 'orders.view'],
        ])->assertRedirect(route('admin.roles.index'));

        $this->assertEqualsCanonicalizing(['support.view', 'orders.view'], $role->permissions()->pluck('slug')->all());

        $this->actingAs($superAdmin)->get(route('admin.roles.edit', 'super_admin'))->assertForbidden();
    }

    public function test_role_edit_page_shows_translated_permission_labels(): void
    {
        $this->actingAs($this->staff(RoleSlug::SuperAdmin))
            ->withHeader('Accept-Language', 'ja')
            ->get(route('admin.roles.edit', 'halal_shop'))
            ->assertOk()
            ->assertSee('操作ログの閲覧')
            ->assertDontSee('admin.permissions.items');
    }
}
