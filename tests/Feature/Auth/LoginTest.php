<?php

namespace Tests\Feature\Auth;

use App\Enums\RoleSlug;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LoginTest extends TestCase
{
    use RefreshDatabase;

    public function test_customer_can_login_and_logout(): void
    {
        $user = $this->customer(['email' => 'hanako@example.com']);

        $this->post(route('login'), ['email' => 'hanako@example.com', 'password' => 'password'])
            ->assertRedirect(route('account.dashboard'));
        $this->assertAuthenticatedAs($user);
        $this->assertNotNull($user->fresh()->last_login_at);

        $this->post(route('logout'))->assertRedirect(route('home'));
        $this->assertGuest();
    }

    public function test_invalid_credentials_are_rejected(): void
    {
        $this->customer(['email' => 'hanako@example.com']);

        $this->post(route('login'), ['email' => 'hanako@example.com', 'password' => 'wrong'])
            ->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_suspended_account_cannot_login(): void
    {
        User::factory()->customer()->suspended()->create(['email' => 'blocked@example.com']);

        $this->post(route('login'), ['email' => 'blocked@example.com', 'password' => 'password'])
            ->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_login_is_rate_limited(): void
    {
        $this->customer(['email' => 'hanako@example.com']);

        for ($i = 0; $i < 5; $i++) {
            $this->post(route('login'), ['email' => 'hanako@example.com', 'password' => 'wrong']);
        }

        $response = $this->post(route('login'), ['email' => 'hanako@example.com', 'password' => 'password']);

        $this->assertGuest();
        $this->assertTrue(in_array($response->status(), [302, 429], true));
    }

    public function test_customer_cannot_use_admin_login(): void
    {
        $this->customer(['email' => 'hanako@example.com']);

        $this->post(route('admin.login'), ['email' => 'hanako@example.com', 'password' => 'password'])
            ->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_staff_login_and_logout_are_audited(): void
    {
        $admin = $this->staff(RoleSlug::SuperAdmin, ['email' => 'admin@example.com']);

        $this->post(route('admin.login'), ['email' => 'admin@example.com', 'password' => 'password'])
            ->assertRedirect(route('admin.dashboard'));
        $this->post(route('logout'))->assertRedirect(route('admin.login'));

        $this->assertDatabaseHas(AuditLog::class, ['user_id' => $admin->id, 'action' => 'admin.login']);
        $this->assertDatabaseHas(AuditLog::class, ['user_id' => $admin->id, 'action' => 'admin.logout']);
    }

    public function test_suspended_user_session_is_terminated_on_next_request(): void
    {
        $user = $this->customer();
        $this->actingAs($user)->get(route('account.dashboard'))->assertOk();

        $user->forceFill(['status' => 'suspended'])->save();

        $this->get(route('account.dashboard'))->assertRedirect(route('login'));
        $this->assertGuest();
    }
}
