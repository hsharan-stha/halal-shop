<?php

namespace Tests\Feature\Admin;

use App\Enums\RoleSlug;
use App\Models\Product;
use App\Models\TaxClass;
use Database\Seeders\TaxSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TaxManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(TaxSeeder::class);
    }

    public function test_tax_screen_shows_the_current_rates(): void
    {
        $this->actingAs($this->staff(RoleSlug::SuperAdmin))
            ->get(route('admin.tax.index'))
            ->assertOk()
            ->assertSee(__('admin.tax.title'))
            ->assertSee('標準税率')
            ->assertSee('10%')
            ->assertSee('8%');
    }

    public function test_a_halal_shop_cannot_view_or_change_tax_rates(): void
    {
        $reduced = TaxClass::query()->where('code', 'reduced')->firstOrFail();
        $shopUser = $this->staff(RoleSlug::HalalShop);

        $this->actingAs($shopUser)
            ->get(route('admin.tax.index'))
            ->assertForbidden();

        $this->actingAs($shopUser)
            ->put(route('admin.tax.update', $reduced), $this->payload($reduced, '9'))
            ->assertForbidden();
    }

    public function test_a_new_rate_must_not_overlap_and_then_applies_on_its_start_date(): void
    {
        $reduced = TaxClass::query()->where('code', 'reduced')->firstOrFail();
        $admin = $this->staff(RoleSlug::SuperAdmin);

        $this->actingAs($admin)
            ->put(route('admin.tax.update', $reduced), $this->payload($reduced, '10', '2030-01-01'))
            ->assertSessionHasErrors('rates');

        $this->actingAs($admin)
            ->put(route('admin.tax.update', $reduced), $this->payload($reduced, '10', '2030-01-01', '2029-12-31'))
            ->assertRedirect(route('admin.tax.edit', $reduced));

        $reduced->load('rates');

        $this->assertSame(800, $reduced->rateOn(now())?->rate_bps);
        $this->assertSame(1000, $reduced->rateOn(now()->setDate(2030, 1, 1))?->rate_bps);
    }

    public function test_the_default_class_and_a_class_used_by_products_cannot_be_deleted(): void
    {
        $reduced = TaxClass::query()->where('code', 'reduced')->firstOrFail();
        $admin = $this->staff(RoleSlug::SuperAdmin);

        $this->actingAs($admin)
            ->delete(route('admin.tax.destroy', $reduced))
            ->assertSessionHas('error');

        $this->assertModelExists($reduced);

        $extra = TaxClass::query()->create([
            'code' => 'exempt',
            'name' => ['ja' => '非課税', 'en' => 'Exempt'],
            'is_default' => false,
        ]);
        $extra->rates()->create(['rate_bps' => 0, 'effective_from' => '2019-10-01']);
        Product::factory()->create(['tax_class_id' => $extra->id]);

        $this->actingAs($admin)
            ->delete(route('admin.tax.destroy', $extra))
            ->assertSessionHas('error');

        $this->assertModelExists($extra);
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(TaxClass $class, string $nextPercent, ?string $nextFrom = null, ?string $currentUntil = null): array
    {
        $current = $class->rates()->firstOrFail();

        $rates = [[
            'id' => $current->id,
            'percent' => '8',
            'effective_from' => '2014-04-01',
            'effective_to' => $currentUntil ?? '',
        ]];

        if ($nextFrom !== null) {
            $rates[] = [
                'percent' => $nextPercent,
                'effective_from' => $nextFrom,
                'effective_to' => '',
            ];
        }

        return [
            'name' => ['ja' => '軽減税率（飲食料品）', 'en' => 'Reduced rate (food & beverages)'],
            'is_default' => '1',
            'rates' => $rates,
        ];
    }
}
