<?php

namespace Tests\Feature\Shop;

use App\Enums\HalalStatus;
use App\Enums\StorageType;
use App\Models\Brand;
use App\Models\Category;
use App\Models\HalalCertification;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Services\Inventory\InventoryService;
use Database\Seeders\TaxSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StorefrontTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(TaxSeeder::class);
    }

    public function test_shop_lists_published_products_and_hides_drafts(): void
    {
        $published = Product::factory()->create(['name' => 'Visible Lamb Cuts']);
        $draft = Product::factory()->draft()->create(['name' => 'Hidden Draft Lamb']);

        $this->get(route('home'))
            ->assertOk()
            ->assertSee('Visible Lamb Cuts')
            ->assertDontSee('Hidden Draft Lamb');

        $this->get(route('shop.index'))
            ->assertOk()
            ->assertSee('Visible Lamb Cuts')
            ->assertDontSee('Hidden Draft Lamb')
            ->assertDontSee(__('shop.nav.cart'));

        $this->get(route('products.show', $published->slug))->assertOk()->assertSee('Visible Lamb Cuts');
        $this->get(route('products.show', $draft->slug))->assertNotFound();
    }

    public function test_certified_badge_requires_a_verified_unexpired_certificate(): void
    {
        $certified = Product::factory()->halal(HalalStatus::Certified)->create(['name' => 'Truly Certified Lamb']);
        $certificate = HalalCertification::factory()->verified()->create(['certificate_number' => 'SHC-VALID-1']);
        $certified->halalCertifications()->attach($certificate);

        $claimed = Product::factory()->halal(HalalStatus::Certified)->create(['name' => 'Claimed Only Lamb']);

        $expired = Product::factory()->halal(HalalStatus::Certified)->create(['name' => 'Expired Cert Lamb']);
        $expired->halalCertifications()->attach(
            HalalCertification::factory()->verified()->expiresOn(now()->subDay())->create(['certificate_number' => 'EXPIRED-999']),
        );

        $this->get(route('shop.index', ['halal' => 'certified']))
            ->assertOk()
            ->assertSee('Truly Certified Lamb')
            ->assertDontSee('Claimed Only Lamb')
            ->assertDontSee('Expired Cert Lamb');

        $this->get(route('products.show', $certified->slug))
            ->assertOk()
            ->assertSee(__('enums.halal_status.certified'))
            ->assertSee('SHC-VALID-1')
            ->assertDontSee('certificate.pdf');

        $this->get(route('products.show', $claimed->slug))
            ->assertOk()
            ->assertSeeInOrder(['Claimed Only Lamb', __('enums.halal_status.unverified')]);

        $this->get(route('products.show', $expired->slug))
            ->assertOk()
            ->assertSee(__('enums.halal_status.unverified'))
            ->assertDontSee('EXPIRED-999');
    }

    public function test_food_label_stays_hidden_until_an_administrator_reviews_it(): void
    {
        $product = Product::factory()->create([
            'name' => 'Labeled Beef',
            'ingredients' => ['en' => 'Secret Beef Blend', 'ja' => '牛肉'],
            'manufacturer' => 'Fictional Foods KK',
        ]);

        $this->get(route('products.show', $product->slug))
            ->assertOk()
            ->assertDontSee('Secret Beef Blend')
            ->assertDontSee('Fictional Foods KK');

        $product->forceFill(['food_label_reviewed_at' => now()])->save();

        $this->get(route('products.show', $product->slug))
            ->assertOk()
            ->assertSee('Secret Beef Blend')
            ->assertSee('Fictional Foods KK')
            ->assertSee(__('shop.product.no_allergens_listed'));
    }

    public function test_search_matches_japanese_names_skus_and_barcodes(): void
    {
        $product = Product::factory()->create([
            'name' => 'English Lamb Shoulder',
            'japanese_name' => 'ラム肩サンプル',
        ]);
        $product->variants()->first()->update([
            'sku' => 'LAMB-SKU-9',
            'barcode' => '4512345678901',
        ]);

        $this->get(route('search'))
            ->assertOk()
            ->assertSee(__('shop.search.prompt'))
            ->assertDontSee('English Lamb Shoulder');

        $this->get(route('search', ['q' => 'ラム肩']))
            ->assertOk()
            ->assertSee('English Lamb Shoulder');

        $this->get(route('search', ['q' => 'LAMB-SKU-9']))->assertOk()->assertSee('English Lamb Shoulder');
        $this->get(route('search', ['q' => '4512345678901']))->assertOk()->assertSee('English Lamb Shoulder');
        $this->get(route('search', ['q' => '%']))->assertOk()->assertDontSee('English Lamb Shoulder');
    }

    public function test_filters_cover_child_categories_price_and_stock(): void
    {
        $parent = Category::factory()->create(['name' => 'Parent Meats']);
        $child = Category::factory()->create(['name' => 'Child Beef', 'parent_id' => $parent->id]);
        $inCategory = Product::factory()->priced(2500)->create([
            'name' => 'Child Category Beef',
            'category_id' => $child->id,
        ]);
        $cheap = Product::factory()->priced(300)->create(['name' => 'Cheap Snack Pack']);
        $this->receive($inCategory, 10);

        $this->get(route('categories.show', $parent))
            ->assertOk()
            ->assertSee('Child Category Beef')
            ->assertDontSee('Cheap Snack Pack');

        $this->get(route('categories.index'))->assertOk()->assertSee('Parent Meats');

        $this->get(route('shop.index', ['category' => $parent->slug]))
            ->assertOk()
            ->assertSee('Child Category Beef')
            ->assertDontSee('Cheap Snack Pack');

        $this->get(route('shop.index', ['min_price' => 1000, 'max_price' => 3000]))
            ->assertOk()
            ->assertSee('Child Category Beef')
            ->assertDontSee('Cheap Snack Pack');

        $this->get(route('shop.index'))
            ->assertOk()
            ->assertSee('Child Category Beef')
            ->assertSee('Cheap Snack Pack');

        $this->get(route('shop.index', ['availability' => 'in_stock']))
            ->assertOk()
            ->assertSee('Child Category Beef')
            ->assertDontSee('Cheap Snack Pack');

        $this->get(route('shop.index', ['sort' => 'price_asc']))
            ->assertOk()
            ->assertSeeInOrder(['Cheap Snack Pack', 'Child Category Beef']);
    }

    public function test_inactive_category_and_brand_pages_return_not_found(): void
    {
        $category = Category::factory()->inactive()->create();
        $brand = Brand::factory()->create(['is_active' => false]);

        $this->get(route('categories.show', $category))->assertNotFound();
        $this->get(route('brands.show', $brand))->assertNotFound();
    }

    public function test_product_page_switches_variants_and_reports_low_stock_without_a_cart_button(): void
    {
        $product = Product::factory()->priced(480)->create(['name' => 'Sized Tea']);
        $second = new ProductVariant([
            'sku' => 'TEA-LARGE',
            'price' => 900,
            'is_active' => true,
            'name' => ['en' => 'Large Pack', 'ja' => '大袋'],
        ]);
        $product->variants()->save($second);
        $this->receive($product, 2);

        $this->get(route('products.show', $product->slug))
            ->assertOk()
            ->assertSee('Large Pack')
            ->assertSee('TEA-LARGE')
            ->assertSee(trans_choice('shop.product.low_stock', 2, ['count' => 2]))
            ->assertSee(__('shop.wishlist.add'))
            ->assertDontSee(__('shop.nav.cart'));

        $this->get(route('products.show', ['product' => $product->slug, 'variant' => $second->id]))
            ->assertOk()
            ->assertSee('TEA-LARGE')
            ->assertSee(__('shop.product.out_of_stock'));
    }

    public function test_guest_wishlist_is_idempotent_and_merges_on_login(): void
    {
        $product = Product::factory()->create(['name' => 'Wishlist Lamb']);
        $draft = Product::factory()->draft()->create();
        $customer = $this->customer();

        $this->post(route('wishlist.store', $draft->slug))->assertNotFound();

        $this->post(route('wishlist.store', $product->slug))->assertRedirect();
        $this->post(route('wishlist.store', $product->slug))->assertRedirect();

        $this->get(route('home'))
            ->assertOk()
            ->assertSee(route('account.wishlist'), false)
            ->assertSee('data-wishlist-count="1"', false);

        $this->get(route('account.wishlist'))
            ->assertOk()
            ->assertSee('Wishlist Lamb');

        $this->assertSame([$product->id], session('wishlist'));
        $this->assertDatabaseCount('wishlist_items', 0);

        $this->delete(route('wishlist.destroy', $product->slug))->assertRedirect();
        $this->get(route('account.wishlist'))->assertOk()->assertDontSee('Wishlist Lamb');

        $this->withSession(['wishlist' => [$product->id, $draft->id]])
            ->post('/login', ['email' => $customer->email, 'password' => 'password'])
            ->assertRedirect();

        $this->assertAuthenticatedAs($customer);
        $this->assertDatabaseHas('wishlist_items', [
            'user_id' => $customer->id,
            'product_id' => $product->id,
        ]);
        $this->assertDatabaseMissing('wishlist_items', [
            'user_id' => $customer->id,
            'product_id' => $draft->id,
        ]);

        $this->actingAs($customer)->post(route('wishlist.store', $product->slug))->assertRedirect();
        $this->assertSame(1, $customer->wishlistItems()->count());

        $this->actingAs($customer)
            ->delete(route('wishlist.destroy', $product->slug))
            ->assertRedirect()
            ->assertSessionHas('success', __('shop.wishlist.removed'));

        $this->assertSame(0, $customer->wishlistItems()->count());
    }

    public function test_recently_viewed_products_appear_on_the_home_page(): void
    {
        $product = Product::factory()->create(['name' => 'Recently Opened Dates']);

        $this->get(route('products.show', $product->slug))->assertOk();
        $this->get(route('home'))->assertOk()->assertSee('Recently Opened Dates');
    }

    public function test_brand_and_storage_filters_limit_the_catalog(): void
    {
        $brand = Brand::factory()->create(['name' => 'Fictional Halal Foods']);
        Product::factory()->create([
            'name' => 'Frozen Brand Dumplings',
            'brand_id' => $brand->id,
            'storage_type' => StorageType::Frozen,
        ]);
        Product::factory()->create(['name' => 'Ambient Other Snack', 'storage_type' => StorageType::Ambient]);

        $this->get(route('brands.show', $brand))
            ->assertOk()
            ->assertSee('Frozen Brand Dumplings')
            ->assertDontSee('Ambient Other Snack');

        $this->get(route('shop.index', ['storage' => 'frozen']))
            ->assertOk()
            ->assertSee('Frozen Brand Dumplings')
            ->assertDontSee('Ambient Other Snack');
    }

    private function receive(Product $product, int $quantity): void
    {
        $item = $product->variants()->where('is_default', true)->first()->inventoryItem;

        app(InventoryService::class)->receive($item, $quantity, [
            'expires_at' => now()->addMonths(6)->toDateString(),
        ]);
    }
}
