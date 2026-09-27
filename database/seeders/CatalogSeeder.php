<?php

namespace Database\Seeders;

use App\Enums\Allergen;
use App\Enums\CertificationStatus;
use App\Enums\HalalStatus;
use App\Enums\ProductStatus;
use App\Enums\StorageType;
use App\Models\Brand;
use App\Models\Category;
use App\Models\HalalCertification;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Shop;
use App\Models\Supplier;
use App\Models\TaxClass;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Fictional demo catalogue. Every brand, certifying body and certificate is
 * invented for local development and must never be presented as real.
 */
class CatalogSeeder extends Seeder
{
    public function run(): void
    {
        $shop = $this->mainShop();
        (new UserSeeder)->ensureShopLogin($shop);

        if (Product::withTrashed()->exists()) {
            return;
        }

        $categories = $this->categories();
        $brands = $this->brands();
        $suppliers = $this->suppliers();
        $certificates = $this->certificates($brands);
        $reviewer = User::query()->where('email', 'products@example.com')->first();
        $reduced = TaxClass::query()->where('code', 'reduced')->firstOrFail();

        foreach ($this->products() as $index => $row) {
            [$sku, $name, $japaneseName, $category, $brand, $halal, $storage, $price, $variants, $certificate] = $row + [8 => [], 9 => null];

            $product = new Product([
                'shop_id' => $shop->id,
                'category_id' => $categories[$category]->id,
                'brand_id' => $brands[$brand]->id,
                'supplier_id' => $suppliers[$index % count($suppliers)]->id,
                'tax_class_id' => $reduced->id,
                'sku' => $sku,
                'slug' => Str::slug($name),
                'name' => $name,
                'japanese_name' => $japaneseName,
                'short_description' => ['ja' => $japaneseName.'。デモ用の架空商品です。', 'en' => $name.'. Fictional demo product.'],
                'description' => [
                    'ja' => "{$japaneseName}の説明文です。\nこの商品は開発・デモ用に作成された架空のデータです。",
                    'en' => "Description of {$name}.\nThis product is fictional data created for development and demos.",
                ],
                'status' => $index === 29 ? ProductStatus::Draft : ProductStatus::Active,
                'is_featured' => $index % 6 === 0,
                'published_at' => $index === 29 ? null : now()->subDays(30 - $index),
                'halal_status' => $halal,
                'storage_type' => $storage,
                'country_of_origin' => $brands[$brand]->country_of_origin,
                'manufacturer' => $brands[$brand]->name.' Co., Ltd. (fictional)',
                'net_content' => $variants[0][1] ?? null,
                'min_order_quantity' => 1,
                'max_order_quantity' => 20,
            ]);

            if ($index % 3 !== 2) {
                $product->ingredients = ['ja' => 'デモ原材料A、デモ原材料B、食塩（架空の表示例）', 'en' => 'Demo ingredient A, demo ingredient B, salt (fictional example)'];
                $product->allergens = $index % 4 === 0 ? [Allergen::Wheat, Allergen::Soybean] : null;
                $product->nutrition = ['basis' => '100g', 'energy_kcal' => 100 + $index * 7, 'protein_g' => 5.2, 'fat_g' => 3.1, 'carbohydrate_g' => 12.4, 'salt_g' => 0.8];
                $product->storage_instructions = [
                    'ja' => __('enums.storage_type.'.$storage->value, [], 'ja').'で保存してください。',
                    'en' => match ($storage) {
                        StorageType::Ambient => 'Store at room temperature away from direct sunlight.',
                        StorageType::Chilled => 'Keep refrigerated at 10°C or below.',
                        StorageType::Frozen => 'Keep frozen at -18°C or below.',
                    },
                ];

                if ($reviewer && $index % 2 === 0) {
                    $product->food_label_reviewed_at = now()->subDays(3);
                    $product->food_label_reviewed_by = $reviewer->id;
                }
            }

            $product->save();

            $variantRows = $variants ?: [[$sku, null, $price]];

            foreach ($variantRows as $position => [$variantSku, $label, $variantPrice]) {
                $variant = new ProductVariant([
                    'sku' => $variantSku,
                    'name' => $label ? ['ja' => $label, 'en' => $label] : null,
                    'price' => $variantPrice,
                    'compare_at_price' => $index % 7 === 0 && $position === 0 ? (int) (ceil($variantPrice * 1.2 / 10) * 10) : null,
                    'cost_price' => (int) floor($variantPrice * 0.6),
                    'barcode' => '49'.str_pad((string) (1000000000 + $index * 10 + $position), 11, '0', STR_PAD_LEFT),
                    'weight_grams' => 250 + $position * 250,
                    'is_active' => true,
                    'sort_order' => $position,
                ]);
                $variant->is_default = $position === 0;
                $product->variants()->save($variant);
            }

            if ($certificate) {
                $product->halalCertifications()->attach($certificates[$certificate]->id);
            }
        }
    }

    /**
     * The shop that owns the fictional catalogue.
     */
    private function mainShop(): Shop
    {
        return Shop::query()->firstOrCreate(
            ['slug' => 'main-shop'],
            [
                'name' => 'メイン店舗',
                'prefecture' => '東京都',
                'city' => '千代田区',
                'town' => '丸の内',
                'street' => '1-1-1',
                'postal_code' => '100-0005',
                'latitude' => 35.681236,
                'longitude' => 139.767125,
                'is_active' => true,
            ],
        );
    }

    /**
     * @return array<string, Category>
     */
    private function categories(): array
    {
        $definitions = [
            'meat' => ['Halal Meat', '精肉', null, 'fire'],
            'chicken' => ['Chicken', '鶏肉', 'meat', null],
            'beef-lamb' => ['Beef & Lamb', '牛肉・羊肉', 'meat', null],
            'frozen' => ['Frozen Foods', '冷凍食品', null, 'snowflake'],
            'rice' => ['Rice & Grains', '米・穀物', null, 'box'],
            'spices' => ['Spices & Seasonings', 'スパイス・調味料', null, 'sparkles'],
            'kitchen' => ['Sauces & Pastes', 'ソース・ペースト', 'spices', null],
            'snacks' => ['Snacks & Sweets', 'お菓子・スナック', null, 'star'],
            'drinks' => ['Beverages', '飲料', null, 'cube'],
            'instant' => ['Instant Foods', 'インスタント食品', null, 'clock'],
        ];

        $categories = [];

        foreach ($definitions as $slug => [$name, $japaneseName, $parent, $icon]) {
            $categories[$slug] = Category::query()->create([
                'parent_id' => $parent ? $categories[$parent]->id : null,
                'name' => $name,
                'japanese_name' => $japaneseName,
                'slug' => $slug,
                'description' => ['ja' => $japaneseName.'のカテゴリです。', 'en' => $name.' category.'],
                'icon' => $icon,
                'sort_order' => count($categories),
                'is_active' => true,
            ]);
        }

        return $categories;
    }

    /**
     * @return array<string, Brand>
     */
    private function brands(): array
    {
        $definitions = [
            'hoshizora' => ['Hoshizora Halal Foods', 'ほしぞらハラールフーズ', 'JP'],
            'kampung' => ['Kampung Harvest', 'カンポン・ハーベスト', 'MY'],
            'andalus' => ['Andalus Spice House', 'アンダルス・スパイスハウス', 'TH'],
            'taman' => ['Taman Snack Works', 'タマン・スナックワークス', 'ID'],
            'nile' => ['Nile Valley Pantry', 'ナイルバレー・パントリー', 'EG'],
        ];

        $brands = [];

        foreach ($definitions as $slug => [$name, $japaneseName, $country]) {
            $brands[$slug] = Brand::query()->create([
                'name' => $name,
                'japanese_name' => $japaneseName,
                'slug' => $slug,
                'description' => ['ja' => '架空のデモ用ブランドです。', 'en' => 'Fictional demo brand.'],
                'country_of_origin' => $country,
                'sort_order' => count($brands),
                'is_active' => true,
            ]);
        }

        return $brands;
    }

    /**
     * @return list<Supplier>
     */
    private function suppliers(): array
    {
        return collect([
            ['サンプル食品卸株式会社（架空）', 'SUP-001', '東京都', '千代田区丸の内0-0-1'],
            ['デモ冷凍物流株式会社（架空）', 'SUP-002', '大阪府', '大阪市北区梅田0-0-2'],
            ['テスト輸入商事株式会社（架空）', 'SUP-003', '神奈川県', '横浜市中区海岸通0-0-3'],
        ])->map(fn (array $row) => Supplier::query()->create([
            'name' => $row[0],
            'company_name' => $row[0],
            'code' => $row[1],
            'country_code' => 'JP',
            'contact_name' => '担当 太郎',
            'email' => Str::lower($row[1]).'@example.com',
            'phone' => '03-0000-000'.substr($row[1], -1),
            'postal_code' => '100-000'.substr($row[1], -1),
            'prefecture' => $row[2],
            'address' => $row[3],
            'is_active' => true,
        ]))->all();
    }

    /**
     * @param  array<string, Brand>  $brands
     * @return array<string, HalalCertification>
     */
    private function certificates(array $brands): array
    {
        $verifier = User::query()->where('email', 'admin@example.com')->first();
        $today = HalalCertification::today();

        $definitions = [
            'hoshizora' => ['Demo Halal Association Japan', 'DHAJ-2025-0001', 'hoshizora', $today->copy()->addMonths(14), CertificationStatus::Verified],
            'kampung' => ['Demo Asia Halal Council', 'DAHC-MY-12345', 'kampung', $today->copy()->addMonths(8), CertificationStatus::Verified],
            'andalus' => ['Demo Asia Halal Council', 'DAHC-TH-67890', 'andalus', $today->copy()->addDays(20), CertificationStatus::Verified],
            'taman' => ['Demo Halal Board Indonesia', 'DHBI-00-4321', 'taman', $today->copy()->addMonths(10), CertificationStatus::Pending],
            'nile' => ['Demo Halal Authority Egypt', 'DHAE-7788', 'nile', $today->copy()->subDays(5), CertificationStatus::Verified],
        ];

        $certificates = [];

        foreach ($definitions as $key => [$body, $number, $brand, $expires, $status]) {
            $certificate = new HalalCertification([
                'brand_id' => $brands[$brand]->id,
                'certifying_body' => $body,
                'certificate_number' => $number,
                'scope' => 'Fictional demo certificate — not a real certification.',
                'issued_at' => $expires->copy()->subYears(2)->toDateString(),
                'expires_at' => $expires->toDateString(),
                'notes' => 'Demo data only.',
            ]);
            $certificate->forceFill([
                'status' => $status,
                'verified_by' => $status === CertificationStatus::Verified ? $verifier?->id : null,
                'verified_at' => $status === CertificationStatus::Verified ? now()->subMonths(2) : null,
            ])->save();

            $certificates[$key] = $certificate;
        }

        return $certificates;
    }

    /**
     * [sku, name, japanese name, category, brand, halal status, storage, price, variants [[sku, label, price]], certificate key]
     *
     * @return list<array<int, mixed>>
     */
    private function products(): array
    {
        $certified = HalalStatus::Certified;
        $declared = HalalStatus::ManufacturerDeclared;
        $plant = HalalStatus::NoAnimalIngredients;
        $unverified = HalalStatus::Unverified;
        $ambient = StorageType::Ambient;
        $chilled = StorageType::Chilled;
        $frozen = StorageType::Frozen;

        return [
            ['HSF-CHK-THIGH', 'Halal Chicken Thigh', 'ハラール鶏もも肉', 'chicken', 'hoshizora', $certified, $frozen, 1280, [['HSF-CHK-THIGH-1K', '1kg', 1280], ['HSF-CHK-THIGH-2K', '2kg', 2380]], 'hoshizora'],
            ['HSF-CHK-BREAST', 'Halal Chicken Breast', 'ハラール鶏むね肉', 'chicken', 'hoshizora', $certified, $frozen, 980, [['HSF-CHK-BREAST-1K', '1kg', 980], ['HSF-CHK-BREAST-2K', '2kg', 1880]], 'hoshizora'],
            ['HSF-CHK-WING', 'Halal Chicken Wings', 'ハラール手羽先', 'chicken', 'hoshizora', $certified, $frozen, 890, [], 'hoshizora'],
            ['HSF-CHK-MINCE', 'Halal Minced Chicken', 'ハラール鶏ひき肉', 'chicken', 'hoshizora', $certified, $frozen, 760, [], 'hoshizora'],
            ['HSF-BEEF-CUBE', 'Halal Beef Cubes', 'ハラール牛角切り肉', 'beef-lamb', 'hoshizora', $certified, $frozen, 2480, [['HSF-BEEF-CUBE-500', '500g', 2480], ['HSF-BEEF-CUBE-1K', '1kg', 4680]], 'hoshizora'],
            ['HSF-LAMB-CHOP', 'Halal Lamb Chops', 'ハラールラムチョップ', 'beef-lamb', 'nile', $certified, $frozen, 3280, [], 'nile'],
            ['HSF-BEEF-MINCE', 'Halal Minced Beef', 'ハラール牛ひき肉', 'beef-lamb', 'hoshizora', $certified, $frozen, 1680, [], 'hoshizora'],
            ['KH-SAMOSA', 'Vegetable Samosa', '野菜サモサ', 'frozen', 'kampung', $certified, $frozen, 680, [], 'kampung'],
            ['KH-PARATHA', 'Frozen Paratha', '冷凍パラタ', 'frozen', 'kampung', $certified, $frozen, 540, [['KH-PARATHA-5', '5枚', 540], ['KH-PARATHA-20', '20枚', 1980]], 'kampung'],
            ['KH-NUGGET', 'Chicken Nuggets', 'チキンナゲット', 'frozen', 'kampung', $certified, $frozen, 720, [], 'kampung'],
            ['KH-SATAY', 'Chicken Satay Skewers', 'チキンサテ串', 'frozen', 'kampung', $certified, $frozen, 1180, [], 'kampung'],
            ['KH-RICE-BASMATI', 'Basmati Rice', 'バスマティライス', 'rice', 'kampung', $plant, $ambient, 1480, [['KH-RICE-BASMATI-2K', '2kg', 1480], ['KH-RICE-BASMATI-5K', '5kg', 3280]], null],
            ['KH-RICE-JASMINE', 'Jasmine Rice', 'ジャスミンライス', 'rice', 'kampung', $plant, $ambient, 1280, [], null],
            ['HSF-RICE-KOSHI', 'Domestic White Rice', '国産白米', 'rice', 'hoshizora', $plant, $ambient, 2980, [['HSF-RICE-KOSHI-5K', '5kg', 2980], ['HSF-RICE-KOSHI-10K', '10kg', 5680]], null],
            ['ASH-GARAM', 'Garam Masala', 'ガラムマサラ', 'spices', 'andalus', $certified, $ambient, 480, [], 'andalus'],
            ['ASH-CUMIN', 'Cumin Seeds', 'クミンシード', 'spices', 'andalus', $plant, $ambient, 380, [], null],
            ['ASH-TURMERIC', 'Turmeric Powder', 'ターメリックパウダー', 'spices', 'andalus', $plant, $ambient, 360, [], null],
            ['ASH-CHILLI', 'Chilli Powder', 'チリパウダー', 'spices', 'andalus', $certified, $ambient, 340, [], 'andalus'],
            ['ASH-CURRY-PASTE', 'Green Curry Paste', 'グリーンカレーペースト', 'kitchen', 'andalus', $certified, $ambient, 420, [], 'andalus'],
            ['ASH-SAMBAL', 'Sambal Chilli Paste', 'サンバルチリペースト', 'kitchen', 'andalus', $declared, $ambient, 460, [], null],
            ['NVP-TAHINI', 'Tahini Sesame Paste', 'タヒニ（練りごま）', 'kitchen', 'nile', $plant, $ambient, 780, [], null],
            ['TSW-KEROPOK', 'Prawn Crackers', 'えびせんべい', 'snacks', 'taman', $certified, $ambient, 320, [], 'taman'],
            ['TSW-DATES', 'Premium Dates', 'プレミアムデーツ', 'snacks', 'nile', $plant, $ambient, 980, [['TSW-DATES-500', '500g', 980], ['TSW-DATES-1K', '1kg', 1780]], null],
            ['TSW-COOKIE', 'Butter Cookies', 'バタークッキー', 'snacks', 'taman', $certified, $ambient, 580, [], 'taman'],
            ['TSW-CHIPS', 'Cassava Chips', 'キャッサバチップス', 'snacks', 'taman', $declared, $ambient, 280, [], null],
            ['NVP-MINT-TEA', 'Mint Green Tea', 'ミントグリーンティー', 'drinks', 'nile', $plant, $ambient, 540, [], null],
            ['KH-TEH-TARIK', 'Instant Milk Tea', 'インスタントミルクティー', 'drinks', 'kampung', $certified, $ambient, 620, [], 'kampung'],
            ['TSW-MANGO', 'Mango Juice', 'マンゴージュース', 'drinks', 'taman', $unverified, $chilled, 398, [], null],
            ['KH-NOODLE', 'Instant Curry Noodles', 'インスタントカレー麺', 'instant', 'kampung', $certified, $ambient, 180, [['KH-NOODLE-1', '1食', 180], ['KH-NOODLE-5', '5食パック', 850]], 'kampung'],
            ['HSF-RETORT-CURRY', 'Retort Chicken Curry', 'レトルトチキンカレー', 'instant', 'hoshizora', $certified, $ambient, 420, [], 'hoshizora'],
        ];
    }
}
