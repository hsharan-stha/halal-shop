<?php

namespace Tests\Feature\Admin;

use App\Enums\RoleSlug;
use App\Models\AuditLog;
use App\Services\SettingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class SettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_update_branding_and_it_is_applied_and_audited(): void
    {
        Storage::fake('public');
        $admin = $this->staff(RoleSlug::Admin);

        $this->actingAs($admin)->put(route('admin.settings.update', 'branding'), [
            'application_name' => 'Barakah Mart',
            'application_short_name' => 'Barakah',
            'primary_color' => '#123456',
            'secondary_color' => '#222222',
            'accent_color' => '#ff8800',
            'background_color' => '#fafafa',
            'support_email' => 'help@example.com',
            'dark_mode_enabled' => '1',
            'logo' => UploadedFile::fake()->image('logo.png', 200, 80),
        ])->assertRedirect(route('admin.settings.edit', 'branding'));

        $settings = app(SettingsService::class);
        $this->assertSame('Barakah Mart', $settings->get('branding.application_name'));
        Storage::disk('public')->assertExists($settings->get('branding.logo'));

        $this->get(route('home'))->assertSee('Barakah Mart')->assertSee('--brand-primary:#123456', false);
        $this->assertDatabaseHas(AuditLog::class, ['action' => 'settings.updated', 'user_id' => $admin->id]);
    }

    public function test_invalid_colour_values_are_rejected(): void
    {
        $this->actingAs($this->staff())->put(route('admin.settings.update', 'branding'), [
            'application_name' => 'X',
            'application_short_name' => 'X',
            'primary_color' => 'red;}body{display:none',
            'secondary_color' => '#222222',
            'accent_color' => '#222222',
            'background_color' => '#222222',
        ])->assertSessionHasErrors('primary_color');
    }

    public function test_unknown_settings_keys_are_not_persisted(): void
    {
        $this->actingAs($this->staff())->put(route('admin.settings.update', 'features'), [
            'reviews_enabled' => '0',
            'is_super_admin' => '1',
        ])->assertRedirect();

        $this->assertDatabaseMissing('settings', ['key' => 'is_super_admin']);
        $this->assertFalse(feature('reviews_enabled'));
    }

    public function test_user_without_update_permission_cannot_change_settings(): void
    {
        $this->actingAs($this->staff(RoleSlug::SupportAgent))
            ->put(route('admin.settings.update', 'maintenance'), ['enabled' => '1'])
            ->assertForbidden();

        $this->assertFalse((bool) settings('maintenance.enabled'));
    }

    public function test_maintenance_mode_closes_store_but_not_admin(): void
    {
        $admin = $this->staff();
        $this->actingAs($admin)->put(route('admin.settings.update', 'maintenance'), [
            'enabled' => '1',
            'message' => ['ja' => '只今メンテナンス中', 'en' => 'Down for maintenance'],
        ]);

        $this->get(route('admin.dashboard'))->assertOk();
        $this->get(route('home'))->assertOk();

        auth()->logout();
        $this->withHeader('Accept-Language', 'ja')->get(route('home'))->assertStatus(503)->assertSee('只今メンテナンス中');
        $this->withHeader('Accept-Language', 'en')->get(route('home'))->assertStatus(503)->assertSee('Down for maintenance');
        $this->get(route('admin.login'))->assertOk();
    }
}
