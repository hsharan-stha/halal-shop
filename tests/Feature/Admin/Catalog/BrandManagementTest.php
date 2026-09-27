<?php

namespace Tests\Feature\Admin\Catalog;

use App\Enums\RoleSlug;
use App\Models\Brand;
use App\Models\Product;
use Database\Seeders\TaxSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class BrandManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_brand_can_be_created_with_logo(): void
    {
        Storage::fake(config('shop.media_disk'));

        $this->actingAs($this->staff(RoleSlug::SuperAdmin))
            ->post(route('admin.brands.store'), [
                'name' => 'Sakura Demo Foods',
                'country_of_origin' => 'jp',
                'website_url' => 'https://example.com',
                'is_active' => '1',
                'logo' => UploadedFile::fake()->image('logo.jpg', 300, 300),
            ])
            ->assertRedirect(route('admin.brands.index'));

        $brand = Brand::query()->where('slug', 'sakura-demo-foods')->firstOrFail();
        $this->assertSame('JP', $brand->country_of_origin);
        Storage::disk(config('shop.media_disk'))->assertExists($brand->logo_path);
    }

    public function test_logo_must_be_an_image(): void
    {
        $this->actingAs($this->staff(RoleSlug::SuperAdmin))
            ->post(route('admin.brands.store'), [
                'name' => 'Bad Logo',
                'logo' => UploadedFile::fake()->create('logo.php', 10, 'application/x-php'),
            ])
            ->assertSessionHasErrors('logo');
    }

    public function test_javascript_urls_are_rejected(): void
    {
        $this->actingAs($this->staff(RoleSlug::SuperAdmin))
            ->post(route('admin.brands.store'), ['name' => 'Sneaky', 'website_url' => 'javascript:alert(1)'])
            ->assertSessionHasErrors('website_url');
    }

    public function test_brand_with_products_cannot_be_deleted(): void
    {
        $this->seed(TaxSeeder::class);
        $brand = Brand::factory()->create();
        Product::factory()->create(['brand_id' => $brand->id]);

        $this->actingAs($this->staff(RoleSlug::SuperAdmin))
            ->delete(route('admin.brands.destroy', $brand))
            ->assertSessionHas('error');

        $this->assertNotSoftDeleted($brand);
    }

    public function test_unused_brand_is_soft_deleted(): void
    {
        $brand = Brand::factory()->create();

        $this->actingAs($this->staff(RoleSlug::SuperAdmin))->delete(route('admin.brands.destroy', $brand))->assertRedirect();

        $this->assertSoftDeleted($brand);
    }
}
