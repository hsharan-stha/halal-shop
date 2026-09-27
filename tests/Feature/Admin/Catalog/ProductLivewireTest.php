<?php

namespace Tests\Feature\Admin\Catalog;

use App\Enums\RoleSlug;
use App\Livewire\Admin\ProductImages;
use App\Livewire\Admin\ProductVariants;
use App\Models\Product;
use App\Models\ProductVariant;
use Database\Seeders\TaxSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class ProductLivewireTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(TaxSeeder::class);
    }

    public function test_variant_can_be_added_and_made_default(): void
    {
        $product = Product::factory()->create();
        $this->actingAs($this->staff(RoleSlug::SuperAdmin));

        Livewire::test(ProductVariants::class, ['product' => $product])
            ->call('create')
            ->set('sku', 'big-pack')
            ->set('name_ja', '2kg')
            ->set('price', '2380')
            ->call('save')
            ->assertHasNoErrors();

        $variant = ProductVariant::query()->where('sku', 'BIG-PACK')->firstOrFail();
        $this->assertSame(2380, $variant->price);
        $this->assertSame(['ja' => '2kg'], $variant->name);

        Livewire::test(ProductVariants::class, ['product' => $product])->call('setDefault', $variant->id);

        $this->assertTrue($variant->fresh()->is_default);
        $this->assertSame(1, $product->variants()->where('is_default', true)->count());
    }

    public function test_variant_form_ids_do_not_collide_with_product_form(): void
    {
        $product = Product::factory()->create();
        $this->actingAs($this->staff(RoleSlug::SuperAdmin));

        Livewire::test(ProductVariants::class, ['product' => $product])
            ->call('create')
            ->assertSeeHtml('id="variant-sku"')
            ->assertDontSeeHtml('id="f-sku"');
    }

    public function test_default_variant_cannot_be_deleted(): void
    {
        $product = Product::factory()->create();
        $this->actingAs($this->staff(RoleSlug::SuperAdmin));

        Livewire::test(ProductVariants::class, ['product' => $product])->call('delete', $product->defaultVariant->id);

        $this->assertNotSoftDeleted($product->defaultVariant);
    }

    public function test_variant_of_another_product_cannot_be_edited(): void
    {
        $product = Product::factory()->create();
        $other = Product::factory()->create();
        $this->actingAs($this->staff(RoleSlug::SuperAdmin));

        Livewire::test(ProductVariants::class, ['product' => $product])
            ->call('edit', $other->defaultVariant->id)
            ->assertNotFound();

        Livewire::test(ProductVariants::class, ['product' => $product])
            ->call('delete', $other->defaultVariant->id)
            ->assertNotFound();

        $this->assertNotSoftDeleted($other->defaultVariant);
    }

    public function test_variant_price_must_be_integer_yen(): void
    {
        $product = Product::factory()->create();
        $this->actingAs($this->staff(RoleSlug::SuperAdmin));

        Livewire::test(ProductVariants::class, ['product' => $product])
            ->call('create')
            ->set('price', '99.9')
            ->call('save')
            ->assertHasErrors(['price' => 'integer']);
    }

    public function test_viewer_cannot_change_variants(): void
    {
        $product = Product::factory()->create();
        $this->actingAs($this->customer());

        Livewire::test(ProductVariants::class, ['product' => $product])->call('create')->assertForbidden();
    }

    public function test_images_can_be_uploaded_reordered_and_deleted(): void
    {
        Storage::fake(config('shop.media_disk'));
        $product = Product::factory()->create();
        $this->actingAs($this->staff(RoleSlug::SuperAdmin));

        $component = Livewire::test(ProductImages::class, ['product' => $product])
            ->set('uploads', [UploadedFile::fake()->image('one.jpg', 800, 800), UploadedFile::fake()->image('two.png', 400, 300)])
            ->assertHasNoErrors();

        [$first, $second] = $product->images()->get()->all();
        $this->assertTrue($first->isProcessed());

        $component->call('reorder', $second->id, 0);
        $this->assertSame([$second->id, $first->id], $product->images()->pluck('id')->all());

        $component->set("meta.{$first->id}.ja", '鶏もも肉のパッケージ')->call('saveMeta', $first->id);
        $this->assertSame('鶏もも肉のパッケージ', $first->fresh()->altText());

        $paths = [$first->path, ...array_values($first->renditions)];
        $component->call('delete', $first->id);

        $this->assertModelMissing($first);
        foreach ($paths as $path) {
            Storage::disk(config('shop.media_disk'))->assertMissing($path);
        }
    }

    public function test_non_image_uploads_are_rejected(): void
    {
        Storage::fake(config('shop.media_disk'));
        $product = Product::factory()->create();
        $this->actingAs($this->staff(RoleSlug::SuperAdmin));

        Livewire::test(ProductImages::class, ['product' => $product])
            ->set('uploads', [UploadedFile::fake()->create('payload.svg', 5, 'image/svg+xml')])
            ->assertHasErrors('uploads.0');

        $this->assertSame(0, $product->images()->count());
    }
}
