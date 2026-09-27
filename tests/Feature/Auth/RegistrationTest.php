<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use App\Services\SettingsService;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_page_renders(): void
    {
        $this->get(route('register'))->assertOk()->assertSee(__('shop.auth.register'));
    }

    public function test_customer_can_register_and_receives_verification_email(): void
    {
        Notification::fake();

        $response = $this->post(route('register'), [
            'name' => '佐藤 太郎',
            'email' => 'Taro@Example.com',
            'phone' => '090-1234-5678',
            'password' => 'secret123',
            'password_confirmation' => 'secret123',
            'terms' => '1',
        ]);

        $response->assertRedirect(route('verification.notice'));
        $this->assertAuthenticated();

        $user = User::query()->where('email', 'taro@example.com')->firstOrFail();
        $this->assertTrue($user->hasRole('customer'));
        $this->assertFalse($user->isStaff());
        $this->assertNotNull($user->ulid);
        Notification::assertSentTo($user, VerifyEmail::class);
    }

    public function test_role_or_staff_flags_submitted_during_registration_are_ignored(): void
    {
        $this->post(route('register'), [
            'name' => 'Mallory',
            'email' => 'mallory@example.com',
            'password' => 'secret123',
            'password_confirmation' => 'secret123',
            'terms' => '1',
            'is_staff' => '1',
            'roles' => ['super_admin'],
            'status' => 'active',
        ]);

        $user = User::query()->where('email', 'mallory@example.com')->firstOrFail();
        $this->assertFalse($user->isStaff());
        $this->assertFalse($user->isSuperAdmin());
    }

    public function test_registration_requires_terms_and_valid_phone(): void
    {
        $this->post(route('register'), [
            'name' => 'Test',
            'email' => 'test@example.com',
            'phone' => '12345',
            'password' => 'secret123',
            'password_confirmation' => 'secret123',
        ])->assertSessionHasErrors(['terms', 'phone']);

        $this->assertGuest();
    }

    public function test_registration_can_be_disabled(): void
    {
        app(SettingsService::class)->set('features', 'registration_enabled', false);

        $this->get(route('register'))->assertNotFound();
        $this->post(route('register'), [
            'name' => 'Test',
            'email' => 'test@example.com',
            'password' => 'secret123',
            'password_confirmation' => 'secret123',
            'terms' => '1',
        ])->assertForbidden();
    }
}
