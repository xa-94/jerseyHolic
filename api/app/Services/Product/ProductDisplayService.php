<?php

declare(strict_types=1);

namespace App\Services\Product;

use App\DTOs\SensitivityResult;
use App\Models\Tenant\Product;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

/**
 * 统一商品展示服务（M4-011 / F-PROD-041~042）
 *
 * 整合斗篷逻辑，根据 cloak_mode 决定返回真实商品数据或安全脱敏数据。
 *
 * 依赖：
 *  - CategoryMappingService  — 品类级安全名称映射（M4-002）
 *  - SensitiveGoodsService   — 特货自动识别（M4-003）
 */
class ProductDisplayService
{
    public function __construct(
        private readonly CategoryMappingService $categoryMappingService,
        private readonly SensitiveGoodsService $sensitiveGoodsService,
    ) {}

    /* ================================================================
     |  单商品展示
     | ================================================================ */

    /**
     * 获取单个商品的展示数据
     *
     * @param  int         $productId  商品 ID
     * @param  string|null $cloakMode  'safe' | 'real' | null
     * @param  string      $locale     语言代码
     * @return array|null  商品展示数据，不存在返回 null
     */
    public function getProductForDisplay(int $productId, ?string $cloakMode = null, string $locale = 'en'): ?array
    {
        $product = Product::with(['descriptions', 'images', 'skus', 'categories', 'attributeValues'])
            ->active()
            ->find($productId);

        if ($product === null) {
            return null;
        }

        return $this->buildDisplayData($product, $cloakMode, $locale);
    }

    /* ================================================================
     |  批量商品展示
     | ================================================================ */

    /**
     * 批量获取商品展示数据
     *
     * @param  array       $productIds  商品 ID 列表
     * @param  string|null $cloakMode   'safe' | 'real' | null
     * @param  string      $locale      语言代码
     * @return Collection  商品展示数据集合（key = product_id）
     */
    public function getProductListForDisplay(array $productIds, ?string $cloakMode = null, string $locale = 'en'): Collection
    {
        if (empty($productIds)) {
            return collect();
        }

        $products = Product::with(['descriptions', 'images', 'categories'])
            ->active()
            ->whereIn('id', $productIds)
            ->get();

        if ($cloakMode !== 'safe') {
            return $products->mapWithKeys(fn (Product $p) => [
                $p->id => $this->buildDisplayData($p, $cloakMode, $locale),
            ]);
        }

        // safe 模式：批量解析安全名称 + 批量判定特货
        $storeId = $this->getCurrentStoreId();

        $productArray = $products->map(fn (Product $p) => [
            'sku'            => $p->sku,
            'category_l1_id' => $this->getPrimaryCategoryL1Id($p),
            'category_l2_id' => $this->getPrimaryCategoryL2Id($p),
            'brand'          => $this->extractBrand($p),
        ])->all();

        $safeNames = $this->categoryMappingService->resolveForProducts($storeId, $productArray, $locale);

        $sensitivityItems = array_map(fn (array $item) => [
            'sku'            => $item['sku'],
            'brand'          => $item['brand'],
            'category_l1_id' => $item['category_l1_id'],
        ], $productArray);

        $sensitivityResults = $this->sensitiveGoodsService->identifyBatch($sensitivityItems);

        return $products->mapWithKeys(function (Product $p, int $index) use ($safeNames, $sensitivityResults, $locale) {
            $safeName = $safeNames[$p->sku] ?? null;
            $sensitivity = $sensitivityResults[$index] ?? SensitivityResult::safe();

            return [
                $p->id => $this->buildSafeDisplayData($p, $safeName, $sensitivity, $locale),
            ];
        });
    }

    /* ================================================================
     |  API 格式化
     | ================================================================ */

    /**
     * 格式化单个商品为 API 响应格式
     *
     * @param  Product     $product    商品模型
     * @param  string|null $cloakMode  'safe' | 'real' | null
     * @param  string      $locale     语言代码
     * @return array
     */
    public function formatForApi(Product $product, ?string $cloakMode = null, string $locale = 'en'): array
    {
        return $this->buildDisplayData($product, $cloakMode, $locale);
    }

    /* ================================================================
     |  占位图
     | ================================================================ */

    /**
     * 根据一级品类获取通用占位图 URL
     *
     * 优先从配置 `product-sync.cloak.placeholder_images` 读取，
     * 否则返回默认占位图。
     *
     * @param  int|null $categoryL1Id  一级品类 ID
     * @return string   占位图 URL
     */
    public function getPlaceholderImage(?int $categoryL1Id = null): string
    {
        /** @var array<string, string> $mapping */
        $mapping = config('product-sync.cloak.placeholder_images', []);

        if ($categoryL1Id !== null) {
            // 通过 L1 ID 获取 code，再查配置
            $category = \App\Models\Central\ProductCategoryL1::find($categoryL1Id);
            if ($category && isset($mapping[$category->code])) {
                return $mapping[$category->code];
            }
        }

        return $mapping['default'] ?? '/images/placeholders/general.jpg';
    }

    /* ================================================================
     |  构建展示数据（内部方法）
     | ================================================================ */

    /**
     * 构建单商品展示数据（统一入口）
     */
    private function buildDisplayData(Product $product, ?string $cloakMode, string $locale): array
    {
        if ($cloakMode === 'safe') {
            return $this->buildSafeDisplayDataSingle($product, $locale);
        }

        return $this->buildRealDisplayData($product, $locale);
    }

    /**
     * 真实模式：返回原始商品数据
     */
    private function buildRealDisplayData(Product $product, string $locale): array
    {
        $description = $this->getDescription($product, $locale);

        return [
            'id'                => $product->id,
            'sku'               => $product->sku,
            'slug'              => $description?->slug ?? '',
            'name'              => $description?->name ?? '',
            'description'       => $description?->description ?? '',
            'short_description' => $description?->short_description ?? '',
            'price'             => (string) $product->price,
            'effective_price'   => (string) $product->effective_price,
            'special_price'     => $product->special_price ? (string) $product->special_price : null,
            'currency'          => 'USD',
            'image'             => $product->image,
            'images'            => $this->formatImages($product),
            'category'          => $this->formatPrimaryCategory($product, $locale),
            'attributes'        => $this->formatAttributes($product),
            'variants'          => $this->formatVariants($product),
            'stock_status'      => $product->stock_status,
            'is_featured'       => (bool) $product->is_featured,
            'cloak_mode'        => 'real',
        ];
    }

    /**
     * 安全模式（单商品）：查询安全名称 + 判定特货
     */
    private function buildSafeDisplayDataSingle(Product $product, string $locale): array
    {
        $storeId = $this->getCurrentStoreId();
        $categoryL1Id = $this->getPrimaryCategoryL1Id($product);
        $categoryL2Id = $this->getPrimaryCategoryL2Id($product);

        $safeName = $this->categoryMappingService->resolve(
            $storeId,
            $product->sku,
            $categoryL1Id,
            $categoryL2Id,
            $locale,
        );

        $sensitivity = $this->sensitiveGoodsService->identify(
            $product->sku,
            $this->extractBrand($product),
            $categoryL1Id,
        );

        return $this->buildSafeDisplayData($product, $safeName, $sensitivity, $locale);
    }

    /**
     * 安全模式：使用预计算的安全名称和特货结果构建数据
     */
    private function buildSafeDisplayData(
        Product $product,
        ?string $safeName,
        SensitivityResult $sensitivity,
        string $locale,
    ): array {
        $finalName = $safeName ?? 'General Merchandise';
        $categoryL1Id = $this->getPrimaryCategoryL1Id($product);

        // 特货：替换图片为占位图，清除品牌相关属性
        $images = $sensitivity->isSensitive
            ? $this->buildPlaceholderImages($categoryL1Id)
            : $this->formatImages($product);

        $mainImage = $sensitivity->isSensitive
            ? $this->getPlaceholderImage($categoryL1Id)
            : $product->image;

        // 安全属性：过滤品牌相关字段
        $attributes = $sensitivity->isSensitive
            ? $this->filterSensitiveAttributes($product)
            : $this->formatAttributes($product);

        Log::debug('[ProductDisplayService] Safe mode applied.', [
            'product_id'   => $product->id,
            'sku'          => $product->sku,
            'safe_name'    => $finalName,
            'is_sensitive' => $sensitivity->isSensitive,
            'sensitivity'  => $sensitivity->level,
        ]);

        return [
            'id'                => $product->id,
            'sku'               => $product->sku,
            'slug'              => $product->id . '-' . \Illuminate\Support\Str::slug($finalName),
            'name'              => $finalName,
            'description'       => $this->buildSafeDescription($finalName),
            'short_description' => $finalName,
            'price'             => (string) $product->price,
            'effective_price'   => (string) $product->effective_price,
            'special_price'     => $product->special_price ? (string) $product->special_price : null,
            'currency'          => 'USD',
            'image'             => $mainImage,
            'images'            => $images,
            'category'          => $this->formatPrimaryCategory($product, $locale),
            'attributes'        => $attributes,
            'variants'          => $this->formatVariants($product),
            'stock_status'      => $product->stock_status,
            'is_featured'       => (bool) $product->is_featured,
            'cloak_mode'        => 'safe',
        ];
    }

    /* ================================================================
     |  辅助格式化方法
     | ================================================================ */

    /**
     * 获取指定语言的商品描述
     */
    private function getDescription(Product $product, string $locale): ?object
    {
        if (!$product->relationLoaded('descriptions')) {
            return null;
        }

        return $product->descriptions->firstWhere('locale', $locale)
            ?? $product->descriptions->firstWhere('locale', 'en')
            ?? $product->descriptions->first();
    }

    /**
     * 格式化商品图片列表
     */
    private function formatImages(Product $product): array
    {
        if (!$product->relationLoaded('images')) {
            return $product->image ? [['image' => $product->image, 'is_main' => true, 'sort_order' => 0]] : [];
        }

        return $product->images->map(fn ($img) => [
            'image'      => $img->image,
            'is_main'    => (bool) $img->is_main,
            'sort_order' => $img->sort_order,
        ])->all();
    }

    /**
     * 构建占位图列表（safe 模式 + 特货）
     */
    private function buildPlaceholderImages(?int $categoryL1Id): array
    {
        $placeholder = $this->getPlaceholderImage($categoryL1Id);

        return [
            ['image' => $placeholder, 'is_main' => true, 'sort_order' => 0],
        ];
    }

    /**
     * 格式化主品类
     */
    private function formatPrimaryCategory(Product $product, string $locale): ?array
    {
        if (!$product->relationLoaded('categories')) {
            return null;
        }

        $primary = $product->categories->firstWhere('pivot.is_primary', 1)
            ?? $product->categories->first();

        if ($primary === null) {
            return null;
        }

        $name = '';
        if ($primary->relationLoaded('descriptions')) {
            $desc = $primary->descriptions->firstWhere('locale', $locale)
                ?? $primary->descriptions->firstWhere('locale', 'en')
                ?? $primary->descriptions->first();
            $name = $desc?->name ?? '';
        }

        return [
            'id'   => $primary->id,
            'name' => $name,
        ];
    }

    /**
     * 格式化商品属性
     */
    private function formatAttributes(Product $product): array
    {
        if (!$product->relationLoaded('attributeValues')) {
            return [];
        }

        return $product->attributeValues->map(fn ($av) => [
            'attribute_id'   => $av->attribute_id,
            'attribute_name' => $av->relationLoaded('attribute') ? $av->attribute?->name : null,
            'value'          => $av->value,
        ])->all();
    }

    /**
     * 过滤敏感属性（移除品牌等敏感字段）
     */
    private function filterSensitiveAttributes(Product $product): array
    {
        if (!$product->relationLoaded('attributeValues')) {
            return [];
        }

        /** @var array<string> 需要过滤的敏感属性名称（小写） */
        $sensitiveKeys = ['brand', 'brand_name', 'manufacturer', 'team', 'player', 'league'];

        return $product->attributeValues
            ->filter(function ($av) use ($sensitiveKeys) {
                $attrName = strtolower($av->relationLoaded('attribute') ? ($av->attribute?->name ?? '') : '');
                return !in_array($attrName, $sensitiveKeys, true);
            })
            ->map(fn ($av) => [
                'attribute_id'   => $av->attribute_id,
                'attribute_name' => $av->relationLoaded('attribute') ? $av->attribute?->name : null,
                'value'          => $av->value,
            ])
            ->values()
            ->all();
    }

    /**
     * 格式化 SKU 变体
     */
    private function formatVariants(Product $product): array
    {
        if (!$product->relationLoaded('skus')) {
            return [];
        }

        return $product->skus->map(fn ($sku) => [
            'id'            => $sku->id,
            'sku'           => $sku->sku,
            'price'         => (string) $sku->price,
            'quantity'      => $sku->quantity,
            'image'         => $sku->image,
            'option_values' => $sku->option_values ?? [],
        ])->all();
    }

    /**
     * 构建安全描述文案
     */
    private function buildSafeDescription(string $safeName): string
    {
        return "High quality {$safeName}. Comfortable fabric, excellent craftsmanship. Perfect for sports and daily wear.";
    }

    /* ================================================================
     |  上下文辅助
     | ================================================================ */

    /**
     * 获取当前站点 ID
     */
    private function getCurrentStoreId(): int
    {
        // 优先从 tenant context 获取
        if (function_exists('tenant') && tenant() !== null) {
            return (int) tenant()->id;
        }

        return (int) config('product-sync.default_store_id', 1);
    }

    /**
     * 获取商品主品类的 L1 ID
     */
    private function getPrimaryCategoryL1Id(Product $product): ?int
    {
        if (!$product->relationLoaded('categories')) {
            return null;
        }

        $primary = $product->categories->firstWhere('pivot.is_primary', 1)
            ?? $product->categories->first();

        return $primary?->parent_id ?? $primary?->id ?? null;
    }

    /**
     * 获取商品主品类的 L2 ID
     */
    private function getPrimaryCategoryL2Id(Product $product): ?int
    {
        if (!$product->relationLoaded('categories')) {
            return null;
        }

        $primary = $product->categories->firstWhere('pivot.is_primary', 1)
            ?? $product->categories->first();

        // 如果品类有 parent_id，说明自身就是 L2
        if ($primary !== null && $primary->parent_id !== null) {
            return $primary->id;
        }

        return null;
    }

    /**
     * 提取品牌名称
     */
    private function extractBrand(Product $product): ?string
    {
        if (!$product->relationLoaded('attributeValues')) {
            return null;
        }

        $brandAttr = $product->attributeValues->first(function ($av) {
            $name = strtolower($av->relationLoaded('attribute') ? ($av->attribute?->name ?? '') : '');
            return $name === 'brand' || $name === 'brand_name';
        });

        return $brandAttr?->value;
    }
}
