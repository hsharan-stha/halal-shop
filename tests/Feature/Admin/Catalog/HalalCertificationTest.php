<?php

namespace Tests\Feature\Admin\Catalog;

use App\Enums\CertificationStatus;
use App\Enums\HalalStatus;
use App\Enums\RoleSlug;
use App\Models\HalalCertification;
use App\Models\Product;
use App\Models\Shop;
use App\Notifications\HalalCertificateExpiring;
use App\Services\Catalog\HalalCertificationService;
use Database\Seeders\TaxSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class HalalCertificationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(TaxSeeder::class);
        Storage::fake(config('shop.private_disk'));
        Storage::fake(config('shop.media_disk'));
    }

    public function test_certificate_file_is_stored_privately_and_starts_pending(): void
    {
        $product = Product::factory()->create();

        $this->actingAs($this->staff(RoleSlug::SuperAdmin))
            ->post(route('admin.halal-certifications.store'), [
                'certifying_body' => 'Demo Halal Council',
                'certificate_number' => 'DHC-001',
                'issued_at' => now()->subMonth()->toDateString(),
                'expires_at' => now()->addYear()->toDateString(),
                'product_ids' => [$product->id],
                'file' => UploadedFile::fake()->create('certificate.pdf', 200, 'application/pdf'),
            ])
            ->assertRedirect();

        $certificate = HalalCertification::query()->where('certificate_number', 'DHC-001')->firstOrFail();

        $this->assertSame(CertificationStatus::Pending, $certificate->status);
        $this->assertMatchesRegularExpression('#^halal-certificates/[A-Za-z0-9]{40}\.pdf$#', $certificate->file_path);
        Storage::disk(config('shop.private_disk'))->assertExists($certificate->file_path);
        Storage::disk(config('shop.media_disk'))->assertMissing($certificate->file_path);
        $this->assertTrue($certificate->products->contains($product));
    }

    public function test_executable_certificate_uploads_are_rejected(): void
    {
        $this->actingAs($this->staff(RoleSlug::SuperAdmin))
            ->post(route('admin.halal-certifications.store'), [
                'certifying_body' => 'Demo',
                'certificate_number' => 'X-1',
                'expires_at' => now()->addYear()->toDateString(),
                'file' => UploadedFile::fake()->create('shell.php', 5, 'application/x-php'),
            ])
            ->assertSessionHasErrors('file');
    }

    public function test_certificate_file_requires_valid_signature_and_permission(): void
    {
        $certificate = HalalCertification::factory()->withFile()->create();
        Storage::disk(config('shop.private_disk'))->put($certificate->file_path, '%PDF-1.4 demo');

        $manager = $this->staff(RoleSlug::SuperAdmin);
        $signed = app(HalalCertificationService::class)->fileUrl($certificate);

        $this->get($signed)->assertRedirect(route('admin.login'));
        $this->actingAs($manager)->get(route('admin.halal-certifications.file', $certificate))->assertForbidden();
        $this->actingAs($manager)->get($signed.'x')->assertForbidden();
        $this->actingAs($this->customer())->get($signed)->assertForbidden();

        $response = $this->actingAs($manager)->get($signed);
        $response->assertOk();
        $this->assertStringContainsString('sandbox', $response->headers->get('Content-Security-Policy'));
        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
    }

    public function test_signed_certificate_links_expire(): void
    {
        $certificate = HalalCertification::factory()->withFile()->create();
        Storage::disk(config('shop.private_disk'))->put($certificate->file_path, '%PDF-1.4 demo');
        $url = URL::temporarySignedRoute('admin.halal-certifications.file', now()->addMinutes(10), $certificate);

        $this->travel(11)->minutes();

        $this->actingAs($this->staff(RoleSlug::SuperAdmin))->get($url)->assertForbidden();
    }

    public function test_only_staff_with_verify_permission_can_verify(): void
    {
        $certificate = HalalCertification::factory()->withFile()->create();

        $this->actingAs($this->staff(RoleSlug::HalalShop))
            ->post(route('admin.halal-certifications.verify', $certificate))
            ->assertForbidden();

        $admin = $this->staff(RoleSlug::SuperAdmin);
        $this->actingAs($admin)->post(route('admin.halal-certifications.verify', $certificate))->assertSessionHas('success');

        $certificate->refresh();
        $this->assertSame(CertificationStatus::Verified, $certificate->status);
        $this->assertSame($admin->id, $certificate->verified_by);
    }

    public function test_certificate_without_file_or_expired_cannot_be_verified(): void
    {
        $admin = $this->staff(RoleSlug::SuperAdmin);
        $noFile = HalalCertification::factory()->create();
        $expired = HalalCertification::factory()->withFile()->expiresOn(now()->subDay())->create();

        $this->actingAs($admin)->post(route('admin.halal-certifications.verify', $noFile))->assertSessionHas('error');
        $this->actingAs($admin)->post(route('admin.halal-certifications.verify', $expired))->assertSessionHas('error');

        $this->assertSame(CertificationStatus::Pending, $noFile->fresh()->status);
        $this->assertSame(CertificationStatus::Pending, $expired->fresh()->status);
    }

    public function test_changing_verified_certificate_returns_it_to_pending(): void
    {
        $certificate = HalalCertification::factory()->verified()->create();

        $this->actingAs($this->staff(RoleSlug::SuperAdmin))
            ->put(route('admin.halal-certifications.update', $certificate), [
                'certifying_body' => $certificate->certifying_body,
                'certificate_number' => $certificate->certificate_number,
                'expires_at' => now()->addYears(3)->toDateString(),
            ])
            ->assertRedirect();

        $this->assertSame(CertificationStatus::Pending, $certificate->fresh()->status);
        $this->assertNull($certificate->fresh()->verified_by);
    }

    public function test_reject_requires_reason(): void
    {
        $certificate = HalalCertification::factory()->withFile()->create();
        $admin = $this->staff(RoleSlug::SuperAdmin);

        $this->actingAs($admin)->post(route('admin.halal-certifications.reject', $certificate), [])->assertSessionHasErrors('reason');
        $this->actingAs($admin)->post(route('admin.halal-certifications.reject', $certificate), ['reason' => 'Number not in registry'])->assertSessionHas('success');

        $this->assertSame(CertificationStatus::Rejected, $certificate->fresh()->status);
    }

    public function test_product_is_only_certified_with_a_verified_unexpired_certificate(): void
    {
        $product = Product::factory()->halal(HalalStatus::Certified)->create();

        $this->assertFalse($product->isCertifiedHalal());
        $this->assertSame(HalalStatus::Unverified, $product->publicHalalStatus());

        $pending = HalalCertification::factory()->withFile()->create();
        $product->halalCertifications()->attach($pending);
        $this->assertFalse($product->fresh()->isCertifiedHalal());

        $expired = HalalCertification::factory()->verified()->expiresOn(now('Asia/Tokyo')->subDay())->create();
        $product->halalCertifications()->sync([$expired->id]);
        $this->assertFalse($product->fresh()->isCertifiedHalal());
        $this->assertSame(0, Product::query()->certifiedHalal()->count());

        $valid = HalalCertification::factory()->verified()->create();
        $product->halalCertifications()->attach($valid);
        $this->assertTrue($product->fresh()->isCertifiedHalal());
        $this->assertSame(HalalStatus::Certified, $product->fresh()->publicHalalStatus());
        $this->assertSame(1, Product::query()->certifiedHalal()->count());
    }

    public function test_non_certified_statuses_never_become_certified(): void
    {
        $product = Product::factory()->halal(HalalStatus::ManufacturerDeclared)->create();
        $product->halalCertifications()->attach(HalalCertification::factory()->verified()->create());

        $this->assertFalse($product->isCertifiedHalal());
        $this->assertSame(HalalStatus::ManufacturerDeclared, $product->publicHalalStatus());
    }

    public function test_expiry_alerts_are_sent_once_per_threshold(): void
    {
        Notification::fake();
        $recipient = $this->staff(RoleSlug::SuperAdmin);
        $nonRecipient = $this->staff(RoleSlug::HalalShop);
        $nonRecipient->forceFill(['shop_id' => Shop::factory()->create()->id])->save();
        $certificate = HalalCertification::factory()->verified()->expiresOn(now('Asia/Tokyo')->addDays(25))->create([
            'shop_id' => Shop::factory()->create()->id,
        ]);
        HalalCertification::factory()->verified()->expiresOn(now('Asia/Tokyo')->addDays(200))->create();

        $this->artisan('halal:check-expiry')->assertSuccessful();
        $this->artisan('halal:check-expiry')->assertSuccessful();

        Notification::assertSentToTimes($recipient, HalalCertificateExpiring::class, 1);
        Notification::assertNotSentTo($nonRecipient, HalalCertificateExpiring::class);
        $this->assertSame(30, $certificate->fresh()->last_alert_days);

        $this->travel(12)->days();
        $this->artisan('halal:check-expiry')->assertSuccessful();
        Notification::assertSentToTimes($recipient, HalalCertificateExpiring::class, 2);
        $this->assertSame(14, $certificate->fresh()->last_alert_days);
    }

    public function test_expired_certificate_triggers_alert(): void
    {
        Notification::fake();
        $recipient = $this->staff(RoleSlug::SuperAdmin);
        $certificate = HalalCertification::factory()->verified()->expiresOn(now('Asia/Tokyo')->subDays(2))->create();

        $this->artisan('halal:check-expiry')->assertSuccessful();

        Notification::assertSentTo($recipient, HalalCertificateExpiring::class, fn (HalalCertificateExpiring $notification) => $notification->daysLeft === -2);
        $this->assertSame(0, $certificate->fresh()->last_alert_days);
    }

    public function test_halal_dashboard_and_pages_render(): void
    {
        $certificate = HalalCertification::factory()->verified()->create(['certifying_body' => 'Visible Council']);
        $manager = $this->staff(RoleSlug::SuperAdmin);

        $this->actingAs($manager)->get(route('admin.halal-certifications.index'))->assertOk()->assertSee('Visible Council');
        $this->actingAs($manager)->get(route('admin.halal-certifications.index', ['expiry' => 'expired']))->assertOk();
        $this->actingAs($manager)->get(route('admin.halal-certifications.show', $certificate))->assertOk();
        $this->actingAs($manager)->get(route('admin.halal-certifications.create'))->assertOk();
        $this->actingAs($manager)->get(route('admin.halal-certifications.edit', $certificate))->assertOk();
    }
}
