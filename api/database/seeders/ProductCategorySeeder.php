<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Central\ProductCategoryL1;
use App\Models\Central\ProductCategoryL2;
use Illuminate\Database\Seeder;

/**
 * 品类体系初始数据 Seeder（M4-001）
 *
 * 创建 6 大 L1 一级品类 + 15 个 L2 二级品类。
 * 所有 name 字段包含 16 种语言翻译。
 * 使用 updateOrCreate 确保幂等性。
 */
class ProductCategorySeeder extends Seeder
{
    public function run(): void
    {
        $categories = $this->getCategoryData();

        foreach ($categories as $sort => $l1Data) {
            $l1 = ProductCategoryL1::updateOrCreate(
                ['code' => $l1Data['code']],
                [
                    'name'            => $l1Data['name'],
                    'icon'            => $l1Data['icon'] ?? null,
                    'is_sensitive'    => $l1Data['is_sensitive'],
                    'sensitive_ratio' => $l1Data['sensitive_ratio'],
                    'sort_order'      => ($sort + 1) * 10,
                    'status'          => ProductCategoryL1::STATUS_ACTIVE,
                ]
            );

            foreach ($l1Data['children'] as $childSort => $l2Data) {
                ProductCategoryL2::updateOrCreate(
                    ['l1_id' => $l1->id, 'code' => $l2Data['code']],
                    [
                        'name'         => $l2Data['name'],
                        'is_sensitive' => $l2Data['is_sensitive'] ?? $l1Data['is_sensitive'],
                        'sort_order'   => ($childSort + 1) * 10,
                        'status'       => ProductCategoryL2::STATUS_ACTIVE,
                    ]
                );
            }
        }

        $this->command?->info('ProductCategorySeeder: 6 L1 + 15 L2 categories seeded.');
    }

    /**
     * 品类初始数据（6 大 L1 + 15 个 L2）
     *
     * @return array<int, array>
     */
    private function getCategoryData(): array
    {
        return [
            // 1. Jerseys (球衣)
            [
                'code'            => 'jerseys',
                'name'            => [
                    'en' => 'Jerseys', 'zh' => '球衣', 'de' => 'Trikots', 'fr' => 'Maillots',
                    'es' => 'Camisetas', 'it' => 'Maglie', 'pt' => 'Camisas', 'nl' => 'Shirts',
                    'pl' => 'Koszulki', 'sv' => 'Tröjor', 'da' => 'Trøjer', 'ar' => 'قمصان',
                    'tr' => 'Formalar', 'el' => 'Φανέλες', 'ja' => 'ジャージ', 'ko' => '저지',
                ],
                'is_sensitive'    => true,
                'sensitive_ratio' => 100.00,
                'children'        => [
                    [
                        'code' => 'soccer_jerseys',
                        'name' => [
                            'en' => 'Soccer Jerseys', 'zh' => '足球球衣', 'de' => 'Fußballtrikots', 'fr' => 'Maillots de Football',
                            'es' => 'Camisetas de Fútbol', 'it' => 'Maglie da Calcio', 'pt' => 'Camisas de Futebol', 'nl' => 'Voetbalshirts',
                            'pl' => 'Koszulki Piłkarskie', 'sv' => 'Fotbollströjor', 'da' => 'Fodboldtrøjer', 'ar' => 'قمصان كرة القدم',
                            'tr' => 'Futbol Formaları', 'el' => 'Φανέλες Ποδοσφαίρου', 'ja' => 'サッカージャージ', 'ko' => '축구 저지',
                        ],
                    ],
                    [
                        'code' => 'basketball_jerseys',
                        'name' => [
                            'en' => 'Basketball Jerseys', 'zh' => '篮球球衣', 'de' => 'Basketballtrikots', 'fr' => 'Maillots de Basket',
                            'es' => 'Camisetas de Baloncesto', 'it' => 'Maglie da Basket', 'pt' => 'Camisas de Basquete', 'nl' => 'Basketbalshirts',
                            'pl' => 'Koszulki Koszykarskie', 'sv' => 'Baskettröjor', 'da' => 'Basketballtrøjer', 'ar' => 'قمصان كرة السلة',
                            'tr' => 'Basketbol Formaları', 'el' => 'Φανέλες Μπάσκετ', 'ja' => 'バスケットボールジャージ', 'ko' => '농구 저지',
                        ],
                    ],
                    [
                        'code' => 'baseball_jerseys',
                        'name' => [
                            'en' => 'Baseball Jerseys', 'zh' => '棒球球衣', 'de' => 'Baseballtrikots', 'fr' => 'Maillots de Baseball',
                            'es' => 'Camisetas de Béisbol', 'it' => 'Maglie da Baseball', 'pt' => 'Camisas de Beisebol', 'nl' => 'Honkbalshirts',
                            'pl' => 'Koszulki Baseballowe', 'sv' => 'Basebolltröjor', 'da' => 'Baseballtrøjer', 'ar' => 'قمصان البيسبول',
                            'tr' => 'Beyzbol Formaları', 'el' => 'Φανέλες Μπέιζμπολ', 'ja' => '野球ジャージ', 'ko' => '야구 저지',
                        ],
                    ],
                ],
            ],

            // 2. Footwear (鞋类)
            [
                'code'            => 'footwear',
                'name'            => [
                    'en' => 'Footwear', 'zh' => '鞋类', 'de' => 'Schuhe', 'fr' => 'Chaussures',
                    'es' => 'Calzado', 'it' => 'Calzature', 'pt' => 'Calçados', 'nl' => 'Schoenen',
                    'pl' => 'Obuwie', 'sv' => 'Skor', 'da' => 'Fodtøj', 'ar' => 'أحذية',
                    'tr' => 'Ayakkabılar', 'el' => 'Υποδήματα', 'ja' => 'フットウェア', 'ko' => '신발',
                ],
                'is_sensitive'    => true,
                'sensitive_ratio' => 80.00,
                'children'        => [
                    [
                        'code' => 'athletic_shoes',
                        'name' => [
                            'en' => 'Athletic Shoes', 'zh' => '运动鞋', 'de' => 'Sportschuhe', 'fr' => 'Chaussures de Sport',
                            'es' => 'Zapatillas Deportivas', 'it' => 'Scarpe Sportive', 'pt' => 'Tênis Esportivos', 'nl' => 'Sportschoenen',
                            'pl' => 'Buty Sportowe', 'sv' => 'Sportskor', 'da' => 'Sportssko', 'ar' => 'أحذية رياضية',
                            'tr' => 'Spor Ayakkabılar', 'el' => 'Αθλητικά Παπούτσια', 'ja' => 'アスレチックシューズ', 'ko' => '운동화',
                        ],
                    ],
                    [
                        'code' => 'casual_sneakers',
                        'name' => [
                            'en' => 'Casual Sneakers', 'zh' => '休闲运动鞋', 'de' => 'Freizeitsneaker', 'fr' => 'Baskets Décontractées',
                            'es' => 'Zapatillas Casuales', 'it' => 'Sneakers Casual', 'pt' => 'Tênis Casuais', 'nl' => 'Casual Sneakers',
                            'pl' => 'Sneakersy Casual', 'sv' => 'Casual Sneakers', 'da' => 'Casual Sneakers', 'ar' => 'أحذية رياضية كاجوال',
                            'tr' => 'Günlük Spor Ayakkabılar', 'el' => 'Casual Αθλητικά', 'ja' => 'カジュアルスニーカー', 'ko' => '캐주얼 스니커즈',
                        ],
                    ],
                    [
                        'code' => 'cleats',
                        'name' => [
                            'en' => 'Cleats', 'zh' => '钉鞋', 'de' => 'Stollenschuhe', 'fr' => 'Crampons',
                            'es' => 'Tacos', 'it' => 'Tacchetti', 'pt' => 'Chuteiras', 'nl' => 'Noppen',
                            'pl' => 'Korki', 'sv' => 'Dobbar', 'da' => 'Knopper', 'ar' => 'أحذية مسامير',
                            'tr' => 'Kramponlar', 'el' => 'Ποδοσφαιρικά', 'ja' => 'クリート', 'ko' => '클리트',
                        ],
                    ],
                ],
            ],

            // 3. Accessories (配饰)
            [
                'code'            => 'accessories',
                'name'            => [
                    'en' => 'Accessories', 'zh' => '配饰', 'de' => 'Zubehör', 'fr' => 'Accessoires',
                    'es' => 'Accesorios', 'it' => 'Accessori', 'pt' => 'Acessórios', 'nl' => 'Accessoires',
                    'pl' => 'Akcesoria', 'sv' => 'Tillbehör', 'da' => 'Tilbehør', 'ar' => 'إكسسوارات',
                    'tr' => 'Aksesuarlar', 'el' => 'Αξεσουάρ', 'ja' => 'アクセサリー', 'ko' => '액세서리',
                ],
                'is_sensitive'    => false,
                'sensitive_ratio' => 30.00,
                'children'        => [
                    [
                        'code' => 'hats_caps',
                        'name' => [
                            'en' => 'Hats & Caps', 'zh' => '帽子', 'de' => 'Hüte & Kappen', 'fr' => 'Chapeaux & Casquettes',
                            'es' => 'Gorras y Sombreros', 'it' => 'Cappelli', 'pt' => 'Bonés e Chapéus', 'nl' => 'Hoeden & Petten',
                            'pl' => 'Czapki', 'sv' => 'Hattar & Kepsar', 'da' => 'Hatte & Kasketter', 'ar' => 'قبعات',
                            'tr' => 'Şapkalar', 'el' => 'Καπέλα', 'ja' => '帽子・キャップ', 'ko' => '모자',
                        ],
                    ],
                    [
                        'code' => 'scarves',
                        'name' => [
                            'en' => 'Scarves', 'zh' => '围巾', 'de' => 'Schals', 'fr' => 'Écharpes',
                            'es' => 'Bufandas', 'it' => 'Sciarpe', 'pt' => 'Cachecóis', 'nl' => 'Sjaals',
                            'pl' => 'Szaliki', 'sv' => 'Halsdukar', 'da' => 'Halstørklæder', 'ar' => 'أوشحة',
                            'tr' => 'Atkılar', 'el' => 'Κασκόλ', 'ja' => 'スカーフ', 'ko' => '스카프',
                        ],
                    ],
                    [
                        'code' => 'wristbands',
                        'name' => [
                            'en' => 'Wristbands', 'zh' => '腕带', 'de' => 'Armbänder', 'fr' => 'Bracelets',
                            'es' => 'Muñequeras', 'it' => 'Polsini', 'pt' => 'Pulseiras', 'nl' => 'Polsbandjes',
                            'pl' => 'Opaski', 'sv' => 'Armband', 'da' => 'Armbånd', 'ar' => 'أساور المعصم',
                            'tr' => 'Bileklikler', 'el' => 'Περικάρπια', 'ja' => 'リストバンド', 'ko' => '손목밴드',
                        ],
                    ],
                ],
            ],

            // 4. Apparel (服装)
            [
                'code'            => 'apparel',
                'name'            => [
                    'en' => 'Apparel', 'zh' => '服装', 'de' => 'Bekleidung', 'fr' => 'Vêtements',
                    'es' => 'Ropa', 'it' => 'Abbigliamento', 'pt' => 'Vestuário', 'nl' => 'Kleding',
                    'pl' => 'Odzież', 'sv' => 'Kläder', 'da' => 'Beklædning', 'ar' => 'ملابس',
                    'tr' => 'Giyim', 'el' => 'Ρουχισμός', 'ja' => 'アパレル', 'ko' => '의류',
                ],
                'is_sensitive'    => true,
                'sensitive_ratio' => 70.00,
                'children'        => [
                    [
                        'code' => 'shorts',
                        'name' => [
                            'en' => 'Shorts', 'zh' => '短裤', 'de' => 'Shorts', 'fr' => 'Shorts',
                            'es' => 'Pantalones Cortos', 'it' => 'Pantaloncini', 'pt' => 'Shorts', 'nl' => 'Shorts',
                            'pl' => 'Szorty', 'sv' => 'Shorts', 'da' => 'Shorts', 'ar' => 'شورتات',
                            'tr' => 'Şortlar', 'el' => 'Σορτς', 'ja' => 'ショーツ', 'ko' => '반바지',
                        ],
                    ],
                    [
                        'code' => 'jackets',
                        'name' => [
                            'en' => 'Jackets', 'zh' => '夹克', 'de' => 'Jacken', 'fr' => 'Vestes',
                            'es' => 'Chaquetas', 'it' => 'Giacche', 'pt' => 'Jaquetas', 'nl' => 'Jassen',
                            'pl' => 'Kurtki', 'sv' => 'Jackor', 'da' => 'Jakker', 'ar' => 'جاكيتات',
                            'tr' => 'Ceketler', 'el' => 'Μπουφάν', 'ja' => 'ジャケット', 'ko' => '재킷',
                        ],
                    ],
                    [
                        'code' => 'training_wear',
                        'name' => [
                            'en' => 'Training Wear', 'zh' => '训练服', 'de' => 'Trainingskleidung', 'fr' => 'Tenue d\'Entraînement',
                            'es' => 'Ropa de Entrenamiento', 'it' => 'Abbigliamento da Allenamento', 'pt' => 'Roupa de Treino', 'nl' => 'Trainingskleding',
                            'pl' => 'Odzież Treningowa', 'sv' => 'Träningskläder', 'da' => 'Træningstøj', 'ar' => 'ملابس تدريب',
                            'tr' => 'Antrenman Kıyafetleri', 'el' => 'Ρούχα Προπόνησης', 'ja' => 'トレーニングウェア', 'ko' => '트레이닝복',
                        ],
                    ],
                ],
            ],

            // 5. Equipment (装备)
            [
                'code'            => 'equipment',
                'name'            => [
                    'en' => 'Equipment', 'zh' => '装备', 'de' => 'Ausrüstung', 'fr' => 'Équipement',
                    'es' => 'Equipamiento', 'it' => 'Attrezzatura', 'pt' => 'Equipamento', 'nl' => 'Uitrusting',
                    'pl' => 'Sprzęt', 'sv' => 'Utrustning', 'da' => 'Udstyr', 'ar' => 'معدات',
                    'tr' => 'Ekipman', 'el' => 'Εξοπλισμός', 'ja' => '装備', 'ko' => '장비',
                ],
                'is_sensitive'    => false,
                'sensitive_ratio' => 20.00,
                'children'        => [
                    [
                        'code' => 'balls',
                        'name' => [
                            'en' => 'Balls', 'zh' => '球类', 'de' => 'Bälle', 'fr' => 'Ballons',
                            'es' => 'Balones', 'it' => 'Palloni', 'pt' => 'Bolas', 'nl' => 'Ballen',
                            'pl' => 'Piłki', 'sv' => 'Bollar', 'da' => 'Bolde', 'ar' => 'كرات',
                            'tr' => 'Toplar', 'el' => 'Μπάλες', 'ja' => 'ボール', 'ko' => '공',
                        ],
                    ],
                    [
                        'code' => 'bags',
                        'name' => [
                            'en' => 'Bags', 'zh' => '包袋', 'de' => 'Taschen', 'fr' => 'Sacs',
                            'es' => 'Bolsas', 'it' => 'Borse', 'pt' => 'Bolsas', 'nl' => 'Tassen',
                            'pl' => 'Torby', 'sv' => 'Väskor', 'da' => 'Tasker', 'ar' => 'حقائب',
                            'tr' => 'Çantalar', 'el' => 'Τσάντες', 'ja' => 'バッグ', 'ko' => '가방',
                        ],
                    ],
                    [
                        'code' => 'protective_gear',
                        'name' => [
                            'en' => 'Protective Gear', 'zh' => '防护装备', 'de' => 'Schutzausrüstung', 'fr' => 'Équipement de Protection',
                            'es' => 'Equipo de Protección', 'it' => 'Attrezzatura Protettiva', 'pt' => 'Equipamento de Proteção', 'nl' => 'Beschermingsuitrusting',
                            'pl' => 'Sprzęt Ochronny', 'sv' => 'Skyddsutrustning', 'da' => 'Beskyttelsesudstyr', 'ar' => 'معدات الحماية',
                            'tr' => 'Koruyucu Ekipman', 'el' => 'Προστατευτικός Εξοπλισμός', 'ja' => 'プロテクティブギア', 'ko' => '보호 장비',
                        ],
                    ],
                ],
            ],

            // 6. Collectibles (收藏品)
            [
                'code'            => 'collectibles',
                'name'            => [
                    'en' => 'Collectibles', 'zh' => '收藏品', 'de' => 'Sammlerstücke', 'fr' => 'Objets de Collection',
                    'es' => 'Coleccionables', 'it' => 'Collezionabili', 'pt' => 'Colecionáveis', 'nl' => 'Verzamelobjecten',
                    'pl' => 'Kolekcjonerskie', 'sv' => 'Samlarföremål', 'da' => 'Samleobjekter', 'ar' => 'مقتنيات',
                    'tr' => 'Koleksiyon Ürünleri', 'el' => 'Συλλεκτικά', 'ja' => 'コレクティブル', 'ko' => '수집품',
                ],
                'is_sensitive'    => false,
                'sensitive_ratio' => 10.00,
                'children'        => [
                    [
                        'code' => 'trading_cards',
                        'name' => [
                            'en' => 'Trading Cards', 'zh' => '球星卡', 'de' => 'Sammelkarten', 'fr' => 'Cartes à Collectionner',
                            'es' => 'Cromos', 'it' => 'Figurine', 'pt' => 'Cartões Colecionáveis', 'nl' => 'Ruilkaarten',
                            'pl' => 'Karty Kolekcjonerskie', 'sv' => 'Samlarkort', 'da' => 'Samlekort', 'ar' => 'بطاقات تداول',
                            'tr' => 'Koleksiyon Kartları', 'el' => 'Κάρτες Συλλογής', 'ja' => 'トレーディングカード', 'ko' => '트레이딩 카드',
                        ],
                    ],
                    [
                        'code' => 'signed_memorabilia',
                        'name' => [
                            'en' => 'Signed Memorabilia', 'zh' => '签名纪念品', 'de' => 'Signierte Erinnerungsstücke', 'fr' => 'Souvenirs Signés',
                            'es' => 'Recuerdos Firmados', 'it' => 'Memorabilia Autografata', 'pt' => 'Memorabilia Autografada', 'nl' => 'Gesigneerde Memorabilia',
                            'pl' => 'Pamiątki z Autografami', 'sv' => 'Signerade Minnessaker', 'da' => 'Signerede Memorabilia', 'ar' => 'تذكارات موقعة',
                            'tr' => 'İmzalı Hatıra Eşyaları', 'el' => 'Υπογεγραμμένα Αναμνηστικά', 'ja' => 'サイン入りメモラビリア', 'ko' => '사인 기념품',
                        ],
                    ],
                ],
            ],
        ];
    }
}
