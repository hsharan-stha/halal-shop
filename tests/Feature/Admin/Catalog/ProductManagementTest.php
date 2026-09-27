<?php

namespace Tests\Feature\Admin\Catalog;

use App\Enums\Allergen;
use App\Enums\HalalStatus;
use App\Enums\ProductStatus;
use App\Enums\RoleSlug;
use App\Models\Category;
use App\Models\HalalCertification;
use App\Models\Product;
use App\Models\Shop;
use App\Models\TaxClass;
use App\Models\User;
use Database\Seeders\TaxSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ProductManagementTest extends TestCase
{
    use RefreshDatabase;

    private User $manager;

    private Shop $shop;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(TaxSeeder::class);
        $this->shop = Shop::factory()->create();
        $this->manager = $this->staff(RoleSlug::SuperAdmin);
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        return array_replace_recursive([
            'name' => 'Halal Chicken Thigh',
            'shop_id' => $this->shop->id,
            'japanese_name' => 'ハラール鶏もも肉',
            'sku' => 'chk-thigh-1',
            'category_id' => Category::factory()->create()->id,
            'tax_class_id' => TaxClass::query()->where('code', 'reduced')->value('id'),
            'status' => 'active',
            'halal_status' => 'unverified',
            'storage_type' => 'frozen',
            'min_order_quantity' => 1,
            'variant' => ['price' => 1280, 'compare_at_price' => 1480],
        ], $overrides);
    }

    public function test_product_is_created_with_default_variant_and_integer_yen_price(): void
    {
        $this->actingAs($this->manager)->post(route('admin.products.store'), $this->payload())->assertRedirect();

        $product = Product::query()->where('sku', 'CHK-THIGH-1')->firstOrFail();
        $variant = $product->defaultVariant;

        $this->assertSame('halal-chicken-thigh', $product->slug);
        $this->assertSame(ProductStatus::Active, $product->status);
        $this->assertNotNull($product->published_at);
        $this->assertSame(HalalStatus::Unverified, $product->halal_status);
        $this->assertSame('CHK-THIGH-1', $variant->sku);
        $this->assertSame(1280, $variant->price);
        $this->assertSame(1480, $variant->compare_at_price);
    }

    public function test_prices_must_be_whole_yen(): void
    {
        $this->actingAs($this->manager)
            ->post(route('admin.products.store'), $this->payload(['variant' => ['price' => '12.50']]))
            ->assertSessionHasErrors('variant.price');

        $this->actingAs($this->manager)
            ->post(route('admin.products.store'), $this->payload(['variant' => ['price' => -1]]))
            ->assertSessionHasErrors('variant.price');
    }

    public function test_compare_at_price_must_exceed_price(): void
    {
        $this->actingAs($this->manager)
            ->post(route('admin.products.store'), $this->payload(['variant' => ['price' => 1000, 'compare_at_price' => 900]]))
            ->assertSessionHasErrors('variant.compare_at_price');
    }

    public function test_certified_status_requires_a_linked_certificate(): void
    {
        $this->actingAs($this->manager)
            ->post(route('admin.products.store'), $this->payload(['halal_status' => 'certified']))
            ->assertSessionHasErrors('halal_certification_ids');
    }

    public function test_unknown_foreign_ids_are_rejected(): void
    {
        $this->actingAs($this->manager)
            ->post(route('admin.products.store'), $this->payload(['category_id' => 999999, 'brand_id' => 999999, 'halal_certification_ids' => [999999]]))
            ->assertSessionHasErrors(['category_id', 'brand_id', 'halal_certification_ids.0']);
    }

    public function test_food_label_review_is_recorded_and_reset_when_label_changes(): void
    {
        $this->actingAs($this->manager)->post(route('admin.products.store'), $this->payload([
            'ingredients' => ['ja' => '鶏肉', 'en' => 'Chicken'],
            'allergens' => ['chicken'],
            'food_label_reviewed' => '1',
        ]));

        $product = Product::query()->where('sku', 'CHK-THIGH-1')->firstOrFail();
        $this->assertTrue($product->hasReviewedFoodLabel());
        $this->assertSame($this->manager->id, $product->food_label_reviewed_by);
        $this->assertTrue($product->allergens->contains(Allergen::Chicken));

        $update = $this->payload(['sku' => $product->sku, 'category_id' => $product->category_id, 'ingredients' => ['ja' => '鶏肉、食塩', 'en' => 'Chicken, salt'], 'allergens' => ['chicken']]);
        unset($update['variant']);

        $this->actingAs($this->manager)->put(route('admin.products.update', $product), $update)->assertRedirect();

        $this->assertFalse($product->fresh()->hasReviewedFoodLabel());
    }

    public function test_unchanged_label_keeps_review_on_update(): void
    {
        $product = Product::factory()->create(['ingredients' => ['ja' => '米']]);
        $product->forceFill(['food_label_reviewed_at' => now(), 'food_label_reviewed_by' => $this->manager->id])->save();

        $this->actingAs($this->manager)->put(route('admin.products.update', $product), [
            'name' => 'Renamed rice',
            'sku' => $product->sku,
            'category_id' => $product->category_id,
            'tax_class_id' => $product->tax_class_id,
            'status' => 'active',
            'halal_status' => 'unverified',
            'storage_type' => 'ambient',
            'min_order_quantity' => 1,
            'ingredients' => ['ja' => '米'],
        ])->assertRedirect();

        $this->assertTrue($product->fresh()->hasReviewedFoodLabel());
        $this->assertSame('Renamed rice', $product->fresh()->name);
    }

    public function test_duplicate_creates_draft_and_resets_halal_and_label(): void
    {
        $certificate = HalalCertification::factory()->verified()->create();
        $product = Product::factory()->halal(HalalStatus::Certified)->create(['sku' => 'ORIG-1', 'slug' => 'orig']);
        $product->halalCertifications()->attach($certificate);
        $product->forceFill(['food_label_reviewed_at' => now()])->save();

        $this->actingAs($this->manager)->post(route('admin.products.duplicate', $product))->assertRedirect();

        $copy = Product::query()->where('sku', 'ORIG-1-COPY')->firstOrFail();
        $this->assertSame(ProductStatus::Draft, $copy->status);
        $this->assertSame(HalalStatus::Unverified, $copy->halal_status);
        $this->assertNull($copy->food_label_reviewed_at);
        $this->assertCount(0, $copy->halalCertifications);
        $this->assertCount(1, $copy->variants);
        $this->assertNotSame($product->defaultVariant->sku, $copy->defaultVariant->sku);
    }

    public function test_bulk_status_change_and_delete(): void
    {
        $products = Product::factory()->count(3)->create();

        $this->actingAs($this->manager)
            ->post(route('admin.products.bulk'), ['action' => 'archive', 'ids' => $products->pluck('id')->all()])
            ->assertSessionHas('success');

        $products->each(fn (Product $product) => $this->assertSame(ProductStatus::Archived, $product->fresh()->status));

        $this->actingAs($this->manager)
            ->post(route('admin.products.bulk'), ['action' => 'delete', 'ids' => [$products[0]->id]])
            ->assertSessionHas('success');

        $this->assertSoftDeleted($products[0]);
    }

    public function test_bulk_action_requires_permission(): void
    {
        $product = Product::factory()->create();

        $this->actingAs($this->customer())
            ->post(route('admin.products.bulk'), ['action' => 'delete', 'ids' => [$product->id]])
            ->assertForbidden();

        $this->assertNotSoftDeleted($product);
    }

    public function test_deleted_product_can_be_restored(): void
    {
        $product = Product::factory()->create();
        $product->delete();

        $this->actingAs($this->manager)->post(route('admin.products.restore', $product->id))->assertRedirect(route('admin.products.edit', $product));

        $this->assertNotSoftDeleted($product);
    }

    public function test_create_with_images_stores_webp_renditions(): void
    {
        Storage::fake(config('shop.media_disk'));

        $this->actingAs($this->manager)->post(route('admin.products.store'), $this->payload([
            'images' => [UploadedFile::fake()->image('a.jpg', 1600, 1200), UploadedFile::fake()->image('b.png', 500, 500)],
        ]))->assertRedirect();

        $images = Product::query()->where('sku', 'CHK-THIGH-1')->firstOrFail()->images;
        $this->assertCount(2, $images);
        $this->assertTrue($images[0]->isProcessed());
        $this->assertSame(array_keys(config('shop.image_sizes')), array_keys($images[0]->renditions));

        foreach ($images[0]->renditions as $path) {
            Storage::disk(config('shop.media_disk'))->assertExists($path);
        }
    }

    public function test_index_edit_and_create_pages_render(): void
    {
        $product = Product::factory()->create(['name' => 'Visible Product']);

        $this->actingAs($this->manager)->get(route('admin.products.index'))->assertOk()->assertSee('Visible Product');
        $this->actingAs($this->manager)->get(route('admin.products.index', ['status' => 'active', 'halal' => 'certified', 'label' => 'unreviewed', 'q' => 'x', 'trashed' => 1]))->assertOk();
        $this->actingAs($this->manager)->get(route('admin.products.create'))->assertOk();
        $this->actingAs($this->manager)->get(route('admin.products.edit', $product))->assertOk()->assertSee(__('admin.variants.title'));
    }

    public function test_a_customer_cannot_edit_products(): void
    {
        $product = Product::factory()->create();

        $this->actingAs($this->customer())->get(route('admin.products.index'))->assertForbidden();
        $this->actingAs($this->customer())->get(route('admin.products.edit', $product))->assertForbidden();
        $this->actingAs($this->customer())->delete(route('admin.products.destroy', $product))->assertForbidden();
    }
}
