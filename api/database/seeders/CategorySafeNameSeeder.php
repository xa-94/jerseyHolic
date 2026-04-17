<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Central\CategorySafeName;
use App\Models\Central\ProductCategoryL1;
use Illuminate\Database\Seeder;

/**
 * 品类级安全映射名称初始数据 Seeder（M4-002）
 *
 * 为每个 L1 品类提供至少 3 条全局兜底安全名称（16 语言），
 * 管理员可后续通过 API 添加更精确的站点/SKU 级映射。
 * 使用 updateOrCreate 确保幂等性。
 */
class CategorySafeNameSeeder extends Seeder
{
    public function run(): void
    {
        $safeNames = $this->getSafeNameData();

        foreach ($safeNames as $categoryCode => $entries) {
            $l1 = ProductCategoryL1::where('code', $categoryCode)->first();
            if ($l1 === null) {
                $this->command?->warn("CategorySafeNameSeeder: L1 '{$categoryCode}' not found, skipping.");
                continue;
            }

            foreach ($entries as $index => $entry) {
                CategorySafeName::updateOrCreate(
                    [
                        'category_l1_id' => $l1->id,
                        'safe_name_en'   => $entry['en'],
                        'store_id'       => null,
                        'sku_prefix'     => null,
                    ],
                    array_merge(
                        [
                            'category_l1_id' => $l1->id,
                            'category_l2_id' => null,
                            'sku_prefix'     => null,
                            'store_id'       => null,
                            'weight'         => $entry['weight'] ?? 10,
                            'status'         => CategorySafeName::STATUS_ACTIVE,
                        ],
                        $this->buildLocaleFields($entry),
                    ),
                );
            }
        }

        $this->command?->info('CategorySafeNameSeeder: Safe names seeded for 6 L1 categories.');
    }

    /**
     * 构建 safe_name_{locale} 字段数组
     */
    private function buildLocaleFields(array $entry): array
    {
        $fields = [];
        foreach (CategorySafeName::SUPPORTED_LOCALES as $locale) {
            $fields["safe_name_{$locale}"] = $entry[$locale] ?? null;
        }
        return $fields;
    }

    /**
     * 安全名称初始数据（6 大 L1 品类，每个至少 3 条）
     */
    private function getSafeNameData(): array
    {
        return [
            // 1. Jerseys → 运动训练服饰类安全名称
            'jerseys' => [
                [
                    'weight' => 15,
                    'en' => 'Athletic Training Apparel',
                    'zh' => '运动训练服',
                    'de' => 'Sportliches Trainingskleidung',
                    'fr' => 'Vêtement d\'Entraînement Athlétique',
                    'es' => 'Ropa de Entrenamiento Atlético',
                    'it' => 'Abbigliamento da Allenamento Atletico',
                    'pt' => 'Roupa de Treino Atlético',
                    'nl' => 'Atletische Trainingskleding',
                    'pl' => 'Sportowa Odzież Treningowa',
                    'sv' => 'Atletisk Träningskläder',
                    'da' => 'Atletisk Træningstøj',
                    'ar' => 'ملابس التدريب الرياضي',
                    'tr' => 'Atletik Antrenman Giysileri',
                    'el' => 'Αθλητικά Ρούχα Προπόνησης',
                    'ja' => 'アスレチックトレーニングウェア',
                    'ko' => '운동 트레이닝 의류',
                ],
                [
                    'weight' => 12,
                    'en' => 'Sports Performance Wear',
                    'zh' => '运动表现服',
                    'de' => 'Sport-Performance-Kleidung',
                    'fr' => 'Vêtement de Performance Sportive',
                    'es' => 'Ropa Deportiva de Rendimiento',
                    'it' => 'Abbigliamento Sportivo Performance',
                    'pt' => 'Roupa Esportiva de Performance',
                    'nl' => 'Sport Prestatiekleding',
                    'pl' => 'Sportowa Odzież Wydajnościowa',
                    'sv' => 'Sportprestanda Kläder',
                    'da' => 'Sports Præstationstøj',
                    'ar' => 'ملابس الأداء الرياضي',
                    'tr' => 'Spor Performans Giysileri',
                    'el' => 'Αθλητικά Ρούχα Επιδόσεων',
                    'ja' => 'スポーツパフォーマンスウェア',
                    'ko' => '스포츠 퍼포먼스 웨어',
                ],
                [
                    'weight' => 10,
                    'en' => 'Team Training Jersey',
                    'zh' => '团队训练衫',
                    'de' => 'Team-Trainingstrikot',
                    'fr' => 'Maillot d\'Entraînement d\'Équipe',
                    'es' => 'Camiseta de Entrenamiento de Equipo',
                    'it' => 'Maglia da Allenamento di Squadra',
                    'pt' => 'Camisa de Treino de Equipe',
                    'nl' => 'Team Trainingsshirt',
                    'pl' => 'Koszulka Treningowa Drużynowa',
                    'sv' => 'Lagets Träningströja',
                    'da' => 'Holdets Træningstrøje',
                    'ar' => 'قميص تدريب الفريق',
                    'tr' => 'Takım Antrenman Forması',
                    'el' => 'Φανέλα Προπόνησης Ομάδας',
                    'ja' => 'チームトレーニングジャージ',
                    'ko' => '팀 트레이닝 저지',
                ],
            ],

            // 2. Footwear → 运动鞋类安全名称
            'footwear' => [
                [
                    'weight' => 15,
                    'en' => 'Athletic Training Shoes',
                    'zh' => '运动训练鞋',
                    'de' => 'Sportliche Trainingsschuhe',
                    'fr' => 'Chaussures d\'Entraînement Athlétique',
                    'es' => 'Zapatillas de Entrenamiento Atlético',
                    'it' => 'Scarpe da Allenamento Atletico',
                    'pt' => 'Tênis de Treino Atlético',
                    'nl' => 'Atletische Trainingsschoenen',
                    'pl' => 'Sportowe Buty Treningowe',
                    'sv' => 'Atletiska Träningsskor',
                    'da' => 'Atletiske Træningssko',
                    'ar' => 'أحذية التدريب الرياضي',
                    'tr' => 'Atletik Antrenman Ayakkabıları',
                    'el' => 'Αθλητικά Παπούτσια Προπόνησης',
                    'ja' => 'アスレチックトレーニングシューズ',
                    'ko' => '운동 트레이닝 신발',
                ],
                [
                    'weight' => 12,
                    'en' => 'Sports Running Footwear',
                    'zh' => '运动跑鞋',
                    'de' => 'Sport-Laufschuhe',
                    'fr' => 'Chaussures de Course Sportive',
                    'es' => 'Calzado Deportivo para Correr',
                    'it' => 'Calzature Sportive da Corsa',
                    'pt' => 'Calçados Esportivos de Corrida',
                    'nl' => 'Sport Hardloopschoenen',
                    'pl' => 'Sportowe Buty do Biegania',
                    'sv' => 'Sport Löparskor',
                    'da' => 'Sports Løbesko',
                    'ar' => 'أحذية الجري الرياضية',
                    'tr' => 'Spor Koşu Ayakkabıları',
                    'el' => 'Αθλητικά Παπούτσια Τρεξίματος',
                    'ja' => 'スポーツランニングシューズ',
                    'ko' => '스포츠 러닝 신발',
                ],
                [
                    'weight' => 10,
                    'en' => 'Casual Active Sneakers',
                    'zh' => '休闲运动鞋',
                    'de' => 'Lässige Aktive Sneaker',
                    'fr' => 'Baskets Actives Décontractées',
                    'es' => 'Zapatillas Activas Casuales',
                    'it' => 'Sneakers Attive Casual',
                    'pt' => 'Tênis Casuais Ativos',
                    'nl' => 'Casual Actieve Sneakers',
                    'pl' => 'Casualowe Aktywne Sneakersy',
                    'sv' => 'Casual Aktiva Sneakers',
                    'da' => 'Casual Aktive Sneakers',
                    'ar' => 'أحذية رياضية كاجوال نشطة',
                    'tr' => 'Günlük Aktif Spor Ayakkabılar',
                    'el' => 'Casual Ενεργά Αθλητικά',
                    'ja' => 'カジュアルアクティブスニーカー',
                    'ko' => '캐주얼 액티브 스니커즈',
                ],
            ],

            // 3. Accessories → 配饰类安全名称
            'accessories' => [
                [
                    'weight' => 15,
                    'en' => 'Sports Fashion Accessories',
                    'zh' => '运动时尚配饰',
                    'de' => 'Sport-Modeaccessoires',
                    'fr' => 'Accessoires de Mode Sportive',
                    'es' => 'Accesorios de Moda Deportiva',
                    'it' => 'Accessori Moda Sportiva',
                    'pt' => 'Acessórios de Moda Esportiva',
                    'nl' => 'Sport Mode Accessoires',
                    'pl' => 'Sportowe Akcesoria Modowe',
                    'sv' => 'Sport Modetillbehör',
                    'da' => 'Sports Mode Tilbehør',
                    'ar' => 'إكسسوارات الموضة الرياضية',
                    'tr' => 'Spor Moda Aksesuarları',
                    'el' => 'Αθλητικά Αξεσουάρ Μόδας',
                    'ja' => 'スポーツファッションアクセサリー',
                    'ko' => '스포츠 패션 액세서리',
                ],
                [
                    'weight' => 12,
                    'en' => 'Active Lifestyle Accessories',
                    'zh' => '活力生活配饰',
                    'de' => 'Aktive Lifestyle-Accessoires',
                    'fr' => 'Accessoires de Style de Vie Actif',
                    'es' => 'Accesorios de Estilo de Vida Activo',
                    'it' => 'Accessori Stile di Vita Attivo',
                    'pt' => 'Acessórios de Estilo de Vida Ativo',
                    'nl' => 'Actieve Levensstijl Accessoires',
                    'pl' => 'Akcesoria Aktywnego Stylu Życia',
                    'sv' => 'Aktiv Livsstil Tillbehör',
                    'da' => 'Aktiv Livsstil Tilbehør',
                    'ar' => 'إكسسوارات نمط الحياة النشط',
                    'tr' => 'Aktif Yaşam Tarzı Aksesuarları',
                    'el' => 'Αξεσουάρ Ενεργού Τρόπου Ζωής',
                    'ja' => 'アクティブライフスタイルアクセサリー',
                    'ko' => '액티브 라이프스타일 액세서리',
                ],
                [
                    'weight' => 10,
                    'en' => 'Outdoor Sports Gear',
                    'zh' => '户外运动装备',
                    'de' => 'Outdoor-Sportausrüstung',
                    'fr' => 'Équipement de Sport en Plein Air',
                    'es' => 'Equipo de Deporte al Aire Libre',
                    'it' => 'Attrezzatura Sportiva all\'Aperto',
                    'pt' => 'Equipamento Esportivo ao Ar Livre',
                    'nl' => 'Outdoor Sportuitrusting',
                    'pl' => 'Sprzęt Sportowy Outdoor',
                    'sv' => 'Utomhus Sportutrustning',
                    'da' => 'Udendørs Sportsudstyr',
                    'ar' => 'معدات الرياضة الخارجية',
                    'tr' => 'Açık Hava Spor Ekipmanı',
                    'el' => 'Εξοπλισμός Υπαίθριου Αθλητισμού',
                    'ja' => 'アウトドアスポーツギア',
                    'ko' => '아웃도어 스포츠 기어',
                ],
            ],

            // 4. Apparel → 服装类安全名称
            'apparel' => [
                [
                    'weight' => 15,
                    'en' => 'Casual Active Wear',
                    'zh' => '休闲运动服',
                    'de' => 'Lässige Aktivkleidung',
                    'fr' => 'Vêtements Actifs Décontractés',
                    'es' => 'Ropa Activa Casual',
                    'it' => 'Abbigliamento Attivo Casual',
                    'pt' => 'Roupa Ativa Casual',
                    'nl' => 'Casual Actieve Kleding',
                    'pl' => 'Casualowa Odzież Aktywna',
                    'sv' => 'Casual Aktiva Kläder',
                    'da' => 'Casual Aktivt Tøj',
                    'ar' => 'ملابس نشاط كاجوال',
                    'tr' => 'Günlük Aktif Giyim',
                    'el' => 'Casual Ενεργά Ρούχα',
                    'ja' => 'カジュアルアクティブウェア',
                    'ko' => '캐주얼 액티브 웨어',
                ],
                [
                    'weight' => 12,
                    'en' => 'Sports Leisure Clothing',
                    'zh' => '运动休闲服装',
                    'de' => 'Sport-Freizeitkleidung',
                    'fr' => 'Vêtements de Loisirs Sportifs',
                    'es' => 'Ropa Deportiva de Ocio',
                    'it' => 'Abbigliamento Sportivo per il Tempo Libero',
                    'pt' => 'Roupas Esportivas de Lazer',
                    'nl' => 'Sport Vrijetijdskleding',
                    'pl' => 'Sportowa Odzież Rekreacyjna',
                    'sv' => 'Sport Fritidskläder',
                    'da' => 'Sports Fritidstøj',
                    'ar' => 'ملابس رياضية ترفيهية',
                    'tr' => 'Spor Boş Zaman Giysileri',
                    'el' => 'Αθλητικά Ρούχα Αναψυχής',
                    'ja' => 'スポーツレジャーウェア',
                    'ko' => '스포츠 레저 의류',
                ],
                [
                    'weight' => 10,
                    'en' => 'Fitness Training Outfit',
                    'zh' => '健身训练套装',
                    'de' => 'Fitness-Trainingsoutfit',
                    'fr' => 'Tenue d\'Entraînement Fitness',
                    'es' => 'Conjunto de Entrenamiento Fitness',
                    'it' => 'Outfit da Allenamento Fitness',
                    'pt' => 'Conjunto de Treino Fitness',
                    'nl' => 'Fitness Trainingsoutfit',
                    'pl' => 'Strój Treningowy Fitness',
                    'sv' => 'Fitness Träningsoutfit',
                    'da' => 'Fitness Træningsoutfit',
                    'ar' => 'زي تدريب اللياقة البدنية',
                    'tr' => 'Fitness Antrenman Kıyafeti',
                    'el' => 'Αμφίεση Γυμναστικής',
                    'ja' => 'フィットネストレーニングウェア',
                    'ko' => '피트니스 트레이닝 의류',
                ],
            ],

            // 5. Equipment → 装备类安全名称
            'equipment' => [
                [
                    'weight' => 15,
                    'en' => 'Sports Training Equipment',
                    'zh' => '运动训练装备',
                    'de' => 'Sporttrainingsausrüstung',
                    'fr' => 'Équipement d\'Entraînement Sportif',
                    'es' => 'Equipamiento de Entrenamiento Deportivo',
                    'it' => 'Attrezzatura da Allenamento Sportivo',
                    'pt' => 'Equipamento de Treino Esportivo',
                    'nl' => 'Sport Trainingsuitrusting',
                    'pl' => 'Sportowy Sprzęt Treningowy',
                    'sv' => 'Sport Träningsutrustning',
                    'da' => 'Sports Træningsudstyr',
                    'ar' => 'معدات التدريب الرياضي',
                    'tr' => 'Spor Antrenman Ekipmanı',
                    'el' => 'Αθλητικός Εξοπλισμός Προπόνησης',
                    'ja' => 'スポーツトレーニング用品',
                    'ko' => '스포츠 트레이닝 장비',
                ],
                [
                    'weight' => 12,
                    'en' => 'Fitness Workout Gear',
                    'zh' => '健身运动器材',
                    'de' => 'Fitness-Workout-Ausrüstung',
                    'fr' => 'Équipement de Fitness',
                    'es' => 'Equipo de Ejercicio Fitness',
                    'it' => 'Attrezzatura Fitness',
                    'pt' => 'Equipamento de Fitness',
                    'nl' => 'Fitness Workout Uitrusting',
                    'pl' => 'Sprzęt Fitness',
                    'sv' => 'Fitness Träningsredskap',
                    'da' => 'Fitness Træningsudstyr',
                    'ar' => 'معدات اللياقة البدنية',
                    'tr' => 'Fitness Egzersiz Ekipmanı',
                    'el' => 'Εξοπλισμός Γυμναστικής',
                    'ja' => 'フィットネスワークアウトギア',
                    'ko' => '피트니스 운동 장비',
                ],
                [
                    'weight' => 10,
                    'en' => 'Active Recreation Supplies',
                    'zh' => '活力休闲用品',
                    'de' => 'Aktive Freizeitartikel',
                    'fr' => 'Fournitures de Loisirs Actifs',
                    'es' => 'Suministros de Recreación Activa',
                    'it' => 'Forniture per la Ricreazione Attiva',
                    'pt' => 'Suprimentos de Recreação Ativa',
                    'nl' => 'Actieve Recreatie Benodigdheden',
                    'pl' => 'Artykuły Aktywnej Rekreacji',
                    'sv' => 'Aktiv Rekreation Tillbehör',
                    'da' => 'Aktiv Rekreation Forsyninger',
                    'ar' => 'مستلزمات الترفيه النشط',
                    'tr' => 'Aktif Rekreasyon Malzemeleri',
                    'el' => 'Είδη Ενεργής Αναψυχής',
                    'ja' => 'アクティブレクリエーション用品',
                    'ko' => '액티브 레크리에이션 용품',
                ],
            ],

            // 6. Collectibles → 收藏品类安全名称
            'collectibles' => [
                [
                    'weight' => 15,
                    'en' => 'Sports Memorabilia Items',
                    'zh' => '运动纪念品',
                    'de' => 'Sport-Erinnerungsstücke',
                    'fr' => 'Articles de Souvenirs Sportifs',
                    'es' => 'Artículos de Memorabilia Deportiva',
                    'it' => 'Articoli di Memorabilia Sportiva',
                    'pt' => 'Itens de Memorabilia Esportiva',
                    'nl' => 'Sport Memorabilia Artikelen',
                    'pl' => 'Sportowe Pamiątki',
                    'sv' => 'Sport Minnesföremål',
                    'da' => 'Sports Memorabilia Genstande',
                    'ar' => 'عناصر التذكارات الرياضية',
                    'tr' => 'Spor Hatıra Eşyaları',
                    'el' => 'Αθλητικά Αναμνηστικά',
                    'ja' => 'スポーツメモラビリア',
                    'ko' => '스포츠 기념품',
                ],
                [
                    'weight' => 12,
                    'en' => 'Fan Collection Goods',
                    'zh' => '球迷收藏品',
                    'de' => 'Fan-Sammlungsartikel',
                    'fr' => 'Articles de Collection de Fan',
                    'es' => 'Artículos de Colección de Fan',
                    'it' => 'Articoli da Collezione per Fan',
                    'pt' => 'Artigos de Coleção de Fã',
                    'nl' => 'Fan Collectie Artikelen',
                    'pl' => 'Artykuły Kolekcjonerskie dla Fanów',
                    'sv' => 'Fan Samling Varor',
                    'da' => 'Fan Samling Varer',
                    'ar' => 'سلع مجموعة المعجبين',
                    'tr' => 'Taraftar Koleksiyon Ürünleri',
                    'el' => 'Είδη Συλλογής Οπαδών',
                    'ja' => 'ファンコレクショングッズ',
                    'ko' => '팬 컬렉션 상품',
                ],
                [
                    'weight' => 10,
                    'en' => 'Hobby Collectible Items',
                    'zh' => '趣味收藏品',
                    'de' => 'Hobby-Sammlerstücke',
                    'fr' => 'Articles de Collection de Loisir',
                    'es' => 'Artículos de Colección de Hobby',
                    'it' => 'Articoli da Collezione per Hobby',
                    'pt' => 'Itens Colecionáveis de Hobby',
                    'nl' => 'Hobby Verzamelobjecten',
                    'pl' => 'Hobbystyczne Przedmioty Kolekcjonerskie',
                    'sv' => 'Hobby Samlarföremål',
                    'da' => 'Hobby Samleobjekter',
                    'ar' => 'عناصر هواية قابلة للتحصيل',
                    'tr' => 'Hobi Koleksiyon Ürünleri',
                    'el' => 'Συλλεκτικά Αντικείμενα Χόμπι',
                    'ja' => 'ホビーコレクタブル',
                    'ko' => '취미 수집품',
                ],
            ],
        ];
    }
}
