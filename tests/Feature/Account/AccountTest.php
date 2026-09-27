<?php

namespace Tests\Feature\Account;

use App\Enums\UserStatus;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AccountTest extends TestCase
{
    use RefreshDatabase;

    public function test_account_pages_require_authentication(): void
    {
        $this->get(route('account.dashboard'))->assertRedirect(route('login'));
    }

    public function test_account_pages_render(): void
    {
        $user = $this->customer();

        foreach (['account.dashboard', 'account.profile', 'account.security', 'account.settings', 'account.privacy'] as $route) {
            $this->actingAs($user)->get(route($route))->assertOk()->assertHeader('X-Robots-Tag', 'noindex, nofollow');
        }
    }

    public function test_profile_can_be_updated_with_photo_and_phone_is_normalised(): void
    {
        Storage::fake('public');
        $user = $this->customer();

        $this->actingAs($user)->put(route('account.profile.update'), [
            'name' => '新しい名前',
            'email' => $user->email,
            'phone' => '０９０－１１１１－２２２２',
            'date_of_birth' => '1990-05-01',
            'locale' => 'en',
            'photo' => UploadedFile::fake()->image('me.jpg', 300, 300),
        ])->assertSessionHas('success');

        $user->refresh();
        $this->assertSame('新しい名前', $user->name);
        $this->assertSame('09011112222', $user->phone);
        $this->assertSame('en', $user->locale);
        Storage::disk('public')->assertExists($user->profile->profile_photo_path);
        $this->assertStringNotContainsString('me.jpg', $user->profile->profile_photo_path);
    }

    public function test_non_image_upload_is_rejected(): void
    {
        $user = $this->customer();

        $this->actingAs($user)->put(route('account.profile.update'), [
            'name' => 'X',
            'email' => $user->email,
            'locale' => 'ja',
            'photo' => UploadedFile::fake()->create('shell.php', 10, 'application/x-php'),
        ])->assertSessionHasErrors('photo');
    }

    public function test_changing_email_requires_reverification(): void
    {
        $user = $this->customer();

        $this->actingAs($user)->put(route('account.profile.update'), [
            'name' => $user->name,
            'email' => 'changed@example.com',
            'locale' => 'ja',
        ]);

        $this->assertNull($user->fresh()->email_verified_at);
    }

    public function test_password_change_requires_current_password(): void
    {
        $user = $this->customer();

        $this->actingAs($user)->put(route('account.password.update'), [
            'current_password' => 'wrong',
            'password' => 'newpass123',
            'password_confirmation' => 'newpass123',
        ])->assertSessionHasErrors('current_password');

        $this->actingAs($user)->put(route('account.password.update'), [
            'current_password' => 'password',
            'password' => 'newpass123',
            'password_confirmation' => 'newpass123',
        ])->assertSessionHas('success');

        $this->assertTrue(Hash::check('newpass123', $user->fresh()->password));
    }

    public function test_data_export_contains_only_own_data(): void
    {
        $user = $this->customer(['email' => 'me@example.com']);
        $this->customer(['email' => 'other@example.com']);

        $response = $this->actingAs($user)->get(route('account.privacy.export'));

        $response->assertOk()->assertJsonPath('account.email', 'me@example.com');
        $this->assertStringNotContainsString('other@example.com', $response->getContent());
        $this->assertStringNotContainsString('password', $response->getContent());
    }

    public function test_account_deletion_anonymises_and_revokes_access(): void
    {
        $user = $this->customer(['email' => 'delete-me@example.com']);
        $user->createToken('mobile');

        $this->actingAs($user)->delete(route('account.privacy.destroy'), [
            'password' => 'password',
            'confirmation' => '1',
        ])->assertRedirect(route('home'));

        $this->assertGuest();
        $user = $user->fresh() ?? User::withTrashed()->find($user->id);
        $this->assertSoftDeleted($user);
        $this->assertNotSame('delete-me@example.com', $user->email);
        $this->assertSame(UserStatus::Deactivated, $user->status);
        $this->assertSame(0, $user->tokens()->count());

        $this->post(route('login'), ['email' => 'delete-me@example.com', 'password' => 'password'])->assertSessionHasErrors('email');
    }

    public function test_account_deletion_requires_password(): void
    {
        $user = $this->customer();

        $this->actingAs($user)->delete(route('account.privacy.destroy'), ['password' => 'wrong', 'confirmation' => '1'])
            ->assertSessionHasErrors('password');

        $this->assertNotSoftDeleted($user);
    }
}
