<?php

namespace App\Support;

/**
 * The 47 Japanese prefectures in JIS X 0401 order. Stored values are the
 * Japanese names; English names are used for display in the English UI.
 */
final class Prefectures
{
    /**
     * @var array<string, string> Japanese name => English name
     */
    private const ALL = [
        '北海道' => 'Hokkaido', '青森県' => 'Aomori', '岩手県' => 'Iwate', '宮城県' => 'Miyagi', '秋田県' => 'Akita',
        '山形県' => 'Yamagata', '福島県' => 'Fukushima', '茨城県' => 'Ibaraki', '栃木県' => 'Tochigi', '群馬県' => 'Gunma',
        '埼玉県' => 'Saitama', '千葉県' => 'Chiba', '東京都' => 'Tokyo', '神奈川県' => 'Kanagawa', '新潟県' => 'Niigata',
        '富山県' => 'Toyama', '石川県' => 'Ishikawa', '福井県' => 'Fukui', '山梨県' => 'Yamanashi', '長野県' => 'Nagano',
        '岐阜県' => 'Gifu', '静岡県' => 'Shizuoka', '愛知県' => 'Aichi', '三重県' => 'Mie', '滋賀県' => 'Shiga',
        '京都府' => 'Kyoto', '大阪府' => 'Osaka', '兵庫県' => 'Hyogo', '奈良県' => 'Nara', '和歌山県' => 'Wakayama',
        '鳥取県' => 'Tottori', '島根県' => 'Shimane', '岡山県' => 'Okayama', '広島県' => 'Hiroshima', '山口県' => 'Yamaguchi',
        '徳島県' => 'Tokushima', '香川県' => 'Kagawa', '愛媛県' => 'Ehime', '高知県' => 'Kochi', '福岡県' => 'Fukuoka',
        '佐賀県' => 'Saga', '長崎県' => 'Nagasaki', '熊本県' => 'Kumamoto', '大分県' => 'Oita', '宮崎県' => 'Miyazaki',
        '鹿児島県' => 'Kagoshima', '沖縄県' => 'Okinawa',
    ];

    /**
     * @return list<string>
     */
    public static function names(): array
    {
        return array_keys(self::ALL);
    }

    /**
     * Options for a select input, labelled in the current locale.
     *
     * @return array<string, string>
     */
    public static function options(): array
    {
        $options = [];

        foreach (self::ALL as $japanese => $english) {
            $options[$japanese] = self::label($japanese);
        }

        return $options;
    }

    public static function label(?string $prefecture): string
    {
        if ($prefecture === null || $prefecture === '') {
            return '';
        }

        return app()->getLocale() === 'ja' ? $prefecture : (self::ALL[$prefecture] ?? $prefecture);
    }
}
