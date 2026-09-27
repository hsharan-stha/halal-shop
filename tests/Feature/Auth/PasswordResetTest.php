<?php

namespace Tests\Feature\Auth;

use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class PasswordResetTest extends TestCase
{
    use RefreshDatabase;

    public function test_reset_link_is_sent_and_password_can_be_reset(): void
    {
        Notification::fake();
        $user = $this->customer(['email' => 'hanako@example.com']);
        $user->createToken('mobile');

        $this->post(route('password.email'), ['email' => 'hanako@example.com'])->assertSessionHas('status');

        $token = null;
        Notification::assertSentTo($user, ResetPassword::class, function (ResetPassword $notification) use (&$token): bool {
            $token = $notification->token;

            return true;
        });

        $this->post(route('password.update'), [
            'token' => $token,
            'email' => 'hanako@example.com',
            'password' => 'newsecret99',
            'password_confirmation' => 'newsecret99',
        ])->assertRedirect(route('login'));

        $this->assertTrue(Hash::check('newsecret99', $user->fresh()->password));
        $this->assertSame(0, $user->tokens()->count(), 'API tokens must be revoked after a password reset.');
    }

    public function test_unknown_email_gets_same_response_to_prevent_enumeration(): void
    {
        Notification::fake();

        $this->post(route('password.email'), ['email' => 'nobody@example.com'])
            ->assertSessionHas('status', __('passwords.sent_generic'))
            ->assertSessionHasNoErrors();

        Notification::assertNothingSent();
    }
}
