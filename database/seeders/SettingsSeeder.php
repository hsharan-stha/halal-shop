<?php

namespace Database\Seeders;

use App\Models\Setting;
use App\Services\SettingsService;
use Illuminate\Database\Seeder;

/**
 * Seeds initial store settings (only where not already set, so admin changes survive re-seeding).
 */
class SettingsSeeder extends Seeder
{
    public function run(): void
    {
        $initial = [
            'branding' => [
                'application_name' => config('app.name'),
            ],
            'general' => [
                'company_name' => '株式会社サンプル（架空）',
                'company_representative' => '代表 山田 太郎（架空）',
                'company_address' => '〒100-0001 東京都千代田区（架空の住所）',
                'business_hours' => ['ja' => '平日 10:00〜18:00（土日祝休み）', 'en' => 'Weekdays 10:00–18:00 (closed weekends & holidays)'],
            ],
            'halal' => [
                'information_notice' => [
                    'ja' => 'ハラール表示は、各認証機関が発行した認証書、またはメーカーの表示に基づき当店が確認したものです。認証内容の詳細は認証書をご確認ください。',
                    'en' => 'Halal information is based on certificates issued by each certifying body or manufacturer declarations reviewed by our staff. Please refer to the certificate for details.',
                ],
            ],
        ];

        foreach ($initial as $group => $values) {
            foreach ($values as $key => $value) {
                Setting::query()->firstOrCreate(['group' => $group, 'key' => $key], ['value' => $value]);
            }
        }

        app(SettingsService::class)->flush();
    }
}
