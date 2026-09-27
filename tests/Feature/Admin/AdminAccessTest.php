<?php

namespace Tests\Feature\Admin;

use App\Enums\RoleSlug;
use App\Support\Settings\SettingsRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class AdminAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected_to_admin_login(): void
    {
        $this->get(route('admin.dashboard'))->assertRedirect(route('admin.login'));
    }

    public function test_customer_cannot_access_admin(): void
    {
        $this->actingAs($this->customer())->get(route('admin.dashboard'))->assertForbidden();
        $this->actingAs($this->customer())->get(route('admin.settings.edit', 'branding'))->assertForbidden();
    }

    public function test_suspended_staff_cannot_access_admin(): void
    {
        $admin = $this->staff();
        $admin->forceFill(['status' => 'suspended'])->save();

        $this->actingAs($admin)->get(route('admin.dashboard'))->assertRedirect(route('login'));
    }

    /**
     * @return array<string, array{0: RoleSlug, 1: string, 2: int}>
     */
    public static function permissionMatrix(): array
    {
        return [
            'super admin sees settings' => [RoleSlug::SuperAdmin, 'admin.settings.edit', 200],
            'admin sees audit log' => [RoleSlug::Admin, 'admin.audit-logs.index', 200],
            'support agent cannot see settings' => [RoleSlug::SupportAgent, 'admin.settings.edit', 403],
            'content manager cannot see staff' => [RoleSlug::ContentManager, 'admin.staff.index', 403],
            'inventory manager cannot see system health' => [RoleSlug::InventoryManager, 'admin.system.health', 403],
            'order manager sees dashboard' => [RoleSlug::OrderManager, 'admin.dashboard', 200],
        ];
    }

    #[DataProvider('permissionMatrix')]
    public function test_role_permissions_are_enforced(RoleSlug $role, string $route, int $status): void
    {
        $params = $route === 'admin.settings.edit' ? ['general'] : [];

        $this->actingAs($this->staff($role))->get(route($route, $params))->assertStatus($status);
    }

    public function test_admin_pages_render_for_super_admin(): void
    {
        $admin = $this->staff(RoleSlug::SuperAdmin);

        foreach (['admin.dashboard', 'admin.roles.index', 'admin.staff.index', 'admin.staff.create', 'admin.audit-logs.index', 'admin.system.health'] as $route) {
            $this->actingAs($admin)->get(route($route))->assertOk();
        }

        foreach (array_keys(SettingsRegistry::groups()) as $group) {
            $this->actingAs($admin)->get(route('admin.settings.edit', $group))->assertOk();
        }
    }

    public function test_admin_responses_are_not_indexable(): void
    {
        $this->actingAs($this->staff())->get(route('admin.dashboard'))
            ->assertHeader('X-Robots-Tag', 'noindex, nofollow')
            ->assertSee('noindex', false);
    }
}
