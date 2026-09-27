<?php

namespace Tests\Feature\Security;

use Illuminate\Contracts\Http\Kernel;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SecurityBaselineTest extends TestCase
{
    use RefreshDatabase;

    public function test_security_headers_are_present(): void
    {
        $this->get(route('home'))
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('X-Frame-Options', 'SAMEORIGIN')
            ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
    }

    public function test_csrf_middleware_protects_the_web_group(): void
    {
        $groups = $this->app->make(Kernel::class)->getMiddlewareGroups();

        $this->assertContains(PreventRequestForgery::class, $groups['web']);
    }

    public function test_locale_switch_only_accepts_enabled_locales(): void
    {
        $this->post(route('locale.switch', 'en'))->assertRedirect();
        $this->assertSame('en', session('locale'));

        $this->post(route('locale.switch', 'fr'))->assertNotFound();
    }

    public function test_default_locale_is_japanese(): void
    {
        $this->withHeader('Accept-Language', '')->get(route('home'))->assertSee('<html lang="ja">', false);
        $this->withHeader('Accept-Language', 'en-US,en;q=0.8')->get(route('home'))->assertSee('<html lang="en">', false);
    }

    public function test_user_input_is_escaped_in_views(): void
    {
        $user = $this->customer(['name' => '<script>alert(1)</script>']);

        $this->actingAs($user)->get(route('account.dashboard'))
            ->assertDontSee('<script>alert(1)</script>', false)
            ->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false);
    }

    public function test_pwa_manifest_uses_branding(): void
    {
        $this->get(route('pwa.manifest'))
            ->assertOk()
            ->assertJsonPath('display', 'standalone')
            ->assertJsonStructure(['name', 'short_name', 'icons', 'theme_color']);
    }
}
