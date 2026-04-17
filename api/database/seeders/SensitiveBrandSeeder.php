<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Central\SensitiveBrand;
use Illuminate\Database\Seeder;

/**
 * 敏感品牌初始数据 Seeder（M4-003）
 *
 * 初始化常见仿牌品牌黑名单，包含品牌别名。
 * 运行：php artisan db:seed --class=SensitiveBrandSeeder
 */
class SensitiveBrandSeeder extends Seeder
{
    /**
     * 初始品牌黑名单
     *
     * @var array<array{brand_name: string, brand_aliases: list<string>, risk_level: string, reason: string}>
     */
    private const BRANDS = [
        [
            'brand_name'    => 'Nike',
            'brand_aliases' => ['NIKE', 'nike inc', 'Nike, Inc.', 'NIKE INC'],
            'risk_level'    => 'high',
            'reason'        => 'Global top sportswear brand, high IP enforcement',
        ],
        [
            'brand_name'    => 'Adidas',
            'brand_aliases' => ['ADIDAS', 'adidas AG', 'Adidas AG', 'adidas originals'],
            'risk_level'    => 'high',
            'reason'        => 'Global top sportswear brand, high IP enforcement',
        ],
        [
            'brand_name'    => 'Jordan',
            'brand_aliases' => ['JORDAN', 'Air Jordan', 'AIR JORDAN', 'Jordan Brand'],
            'risk_level'    => 'high',
            'reason'        => 'Nike sub-brand, extremely high IP enforcement',
        ],
        [
            'brand_name'    => 'Puma',
            'brand_aliases' => ['PUMA', 'Puma SE', 'PUMA SE'],
            'risk_level'    => 'high',
            'reason'        => 'Major sportswear brand, active IP protection',
        ],
        [
            'brand_name'    => 'Under Armour',
            'brand_aliases' => ['UNDER ARMOUR', 'UA', 'Under Armour, Inc.', 'UnderArmour'],
            'risk_level'    => 'high',
            'reason'        => 'US sportswear brand, active IP protection',
        ],
        [
            'brand_name'    => 'New Balance',
            'brand_aliases' => ['NEW BALANCE', 'NB', 'New Balance Athletics', 'NEWBALANCE'],
            'risk_level'    => 'high',
            'reason'        => 'US sportswear brand, active IP protection',
        ],
        [
            'brand_name'    => 'Reebok',
            'brand_aliases' => ['REEBOK', 'Reebok International'],
            'risk_level'    => 'medium',
            'reason'        => 'Sportswear brand, moderate IP enforcement',
        ],
        [
            'brand_name'    => 'Converse',
            'brand_aliases' => ['CONVERSE', 'Converse Inc.', 'CONVERSE INC'],
            'risk_level'    => 'high',
            'reason'        => 'Nike subsidiary, high IP enforcement',
        ],
        [
            'brand_name'    => 'Gucci',
            'brand_aliases' => ['GUCCI', 'Gucci Group', 'GUCCIO GUCCI'],
            'risk_level'    => 'high',
            'reason'        => 'Luxury brand, extremely high IP enforcement',
        ],
        [
            'brand_name'    => 'Louis Vuitton',
            'brand_aliases' => ['LOUIS VUITTON', 'LV', 'LVMH', 'Louis Vuitton Malletier'],
            'risk_level'    => 'high',
            'reason'        => 'Luxury brand, extremely high IP enforcement',
        ],
        [
            'brand_name'    => 'Supreme',
            'brand_aliases' => ['SUPREME', 'Supreme New York', 'SUPREME NYC'],
            'risk_level'    => 'high',
            'reason'        => 'Streetwear brand, active IP protection',
        ],
        [
            'brand_name'    => 'Champion',
            'brand_aliases' => ['CHAMPION', 'Champion USA', 'Champion Athleticwear'],
            'risk_level'    => 'medium',
            'reason'        => 'Sportswear brand, moderate IP enforcement',
        ],
        [
            'brand_name'    => 'Ralph Lauren',
            'brand_aliases' => ['RALPH LAUREN', 'Polo Ralph Lauren', 'POLO', 'Ralph Lauren Corporation'],
            'risk_level'    => 'high',
            'reason'        => 'Fashion brand, active IP protection',
        ],
        [
            'brand_name'    => 'The North Face',
            'brand_aliases' => ['THE NORTH FACE', 'TNF', 'North Face', 'NORTH FACE'],
            'risk_level'    => 'high',
            'reason'        => 'Outdoor brand, active IP protection',
        ],
        [
            'brand_name'    => 'Balenciaga',
            'brand_aliases' => ['BALENCIAGA', 'Balenciaga SA'],
            'risk_level'    => 'high',
            'reason'        => 'Luxury brand, high IP enforcement',
        ],
    ];

    public function run(): void
    {
        foreach (self::BRANDS as $brandData) {
            SensitiveBrand::updateOrCreate(
                [
                    'brand_name'     => $brandData['brand_name'],
                    'category_l1_id' => null,
                ],
                [
                    'brand_aliases' => $brandData['brand_aliases'],
                    'risk_level'    => $brandData['risk_level'],
                    'reason'        => $brandData['reason'],
                    'status'        => SensitiveBrand::STATUS_ACTIVE,
                ],
            );
        }

        $this->command->info('SensitiveBrandSeeder: ' . count(self::BRANDS) . ' brands seeded.');
    }
}
