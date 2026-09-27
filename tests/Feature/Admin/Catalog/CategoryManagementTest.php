<?php

namespace Tests\Feature\Admin\Catalog;

use App\Enums\RoleSlug;
use App\Models\Category;
use App\Models\Product;
use Database\Seeders\TaxSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CategoryManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_product_manager_can_create_nested_category_with_generated_slug(): void
    {
        $parent = Category::factory()->create();

        $this->actingAs($this->staff(RoleSlug::ProductManager))
            ->post(route('admin.categories.store'), [
                'name' => 'Frozen Chicken',
                'japanese_name' => '冷凍鶏肉',
                'parent_id' => $parent->id,
                'description' => ['ja' => '説明', 'en' => ''],
                'is_active' => '1',
            ])
            ->assertRedirect(route('admin.categories.index'));

        $category = Category::query()->where('slug', 'frozen-chicken')->firstOrFail();
        $this->assertSame($parent->id, $category->parent_id);
        $this->assertSame(['ja' => '説明'], $category->description);
        $this->assertSame('冷凍鶏肉', $category->localizedName('ja'));
        $this->assertSame('Frozen Chicken', $category->localizedName('en'));
    }

    public function test_category_cannot_become_its_own_descendant(): void
    {
        $root = Category::factory()->create();
        $child = Category::factory()->create(['parent_id' => $root->id]);
        $grandchild = Category::factory()->create(['parent_id' => $child->id]);

        $this->actingAs($this->staff(RoleSlug::ProductManager))
            ->put(route('admin.categories.update', $root), ['name' => $root->name, 'slug' => $root->slug, 'parent_id' => $grandchild->id, 'is_active' => '1'])
            ->assertSessionHasErrors('parent_id');

        $this->assertNull($root->fresh()->parent_id);
    }

    public function test_category_with_products_cannot_be_deleted(): void
    {
        $this->seed(TaxSeeder::class);
        $category = Category::factory()->create();
        Product::factory()->create(['category_id' => $category->id]);

        $this->actingAs($this->staff(RoleSlug::ProductManager))
            ->delete(route('admin.categories.destroy', $category))
            ->assertSessionHas('error');

        $this->assertModelExists($category);
    }

    public function test_deleting_category_moves_children_up(): void
    {
        $root = Category::factory()->create();
        $middle = Category::factory()->create(['parent_id' => $root->id]);
        $leaf = Category::factory()->create(['parent_id' => $middle->id]);

        $this->actingAs($this->staff(RoleSlug::ProductManager))->delete(route('admin.categories.destroy', $middle))->assertRedirect();

        $this->assertModelMissing($middle);
        $this->assertSame($root->id, $leaf->fresh()->parent_id);
    }

    public function test_category_image_is_reencoded_with_random_name(): void
    {
        Storage::fake(config('shop.media_disk'));

        $this->actingAs($this->staff(RoleSlug::ProductManager))
            ->post(route('admin.categories.store'), [
                'name' => 'Spices',
                'is_active' => '1',
                'image' => UploadedFile::fake()->image('../../evil.php.png', 600, 400),
            ])
            ->assertRedirect();

        $path = Category::query()->where('slug', 'spices')->value('image_path');
        $this->assertMatchesRegularExpression('#^categories/[A-Za-z0-9]{40}\.webp$#', $path);
        Storage::disk(config('shop.media_disk'))->assertExists($path);
    }

    public function test_view_only_staff_cannot_manage_categories(): void
    {
        $contentManager = $this->staff(RoleSlug::ContentManager);

        $this->actingAs($contentManager)->get(route('admin.categories.index'))->assertOk();
        $this->actingAs($contentManager)->get(route('admin.categories.create'))->assertForbidden();
        $this->actingAs($contentManager)->post(route('admin.categories.store'), ['name' => 'X'])->assertForbidden();
    }

    public function test_staff_without_permission_cannot_view_categories(): void
    {
        $this->actingAs($this->staff(RoleSlug::SupportAgent))->get(route('admin.categories.index'))->assertForbidden();
    }

    public function test_index_renders_tree(): void
    {
        $root = Category::factory()->create(['name' => 'Root Cat']);
        Category::factory()->create(['name' => 'Leaf Cat', 'parent_id' => $root->id]);

        $this->actingAs($this->staff(RoleSlug::ProductManager))
            ->get(route('admin.categories.index'))
            ->assertOk()
            ->assertSeeInOrder(['Root Cat', 'Leaf Cat']);
    }
}
