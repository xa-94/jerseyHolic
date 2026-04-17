<?php

declare(strict_types=1);

namespace App\Services\Product;

use App\DTOs\OrderSensitivityResult;
use App\DTOs\SensitivityResult;
use App\Models\Central\ProductCategoryL1;
use App\Models\Central\SensitiveBrand;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * 特货自动识别服务（M4-003 / F-PROD-020~022）
 *
 * 三级判定引擎：
 *  Level 1 — SKU 前缀匹配（confidence 100%）
 *  Level 2 — 品牌黑名单匹配（confidence 90%）
 *  Level 3 — 品类敏感度判定（confidence = sensitive_ratio%）
 *
 * 混合订单策略（BR-MIX-001~004）：
 *  任一商品判定为特货 → 全订单安全映射
 *
 * 被 ProductSyncService（M4-006）和支付网关调用。
 */
class SensitiveGoodsService
{
    /** 缓存 Key */
    private const CACHE_KEY_BRANDS = 'sensitive_brands_list';

    /** 缓存 TTL（秒） — 30 分钟 */
    private const CACHE_TTL = 1800;

    /** 默认敏感 SKU 前缀（后续迁移至 config/product-sync.php） */
    public const DEFAULT_SENSITIVE_PREFIXES = [
        'hic', 'WPZ', 'DIY', 'NBL', 'NFL', 'NBA', 'MLB', 'NHL',
    ];

    /* ================================================================
     |  核心方法：单商品判定
     | ================================================================ */

    /**
     * 三级判定引擎 — 识别单个商品是否为特货
     *
     * 任一级命中即返回，不再继续判定。
     *
     * @param  string   $sku          商品 SKU
     * @param  string|null $brandName 品牌名称
     * @param  int|null $categoryL1Id 一级品类 ID
     * @return SensitivityResult
     */
    public function identify(string $sku, ?string $brandName = null, ?int $categoryL1Id = null): SensitivityResult
    {
        // Level 1: SKU 前缀匹配
        $skuResult = $this->matchSkuPrefix($sku);
        if ($skuResult !== null) {
            return $skuResult;
        }

        // Level 2: 品牌黑名单匹配
        if ($brandName !== null && $brandName !== '') {
            $brandResult = $this->matchBrand($brandName, $categoryL1Id);
            if ($brandResult !== null) {
                return $brandResult;
            }
        }

        // Level 3: 品类敏感度判定
        if ($categoryL1Id !== null) {
            $categoryResult = $this->matchCategory($categoryL1Id);
            if ($categoryResult !== null) {
                return $categoryResult;
            }
        }

        return SensitivityResult::safe();
    }

    /* ================================================================
     |  混合订单策略（BR-MIX-001~004）
     | ================================================================ */

    /**
     * 分析订单中所有商品的特货状态
     *
     * BR-MIX-001：存在任一特货 → 全订单标记为特货
     * BR-MIX-002：全订单使用安全映射名称
     * BR-MIX-003：支付描述统一使用安全描述
     * BR-MIX-004：物流申报使用安全品名
     *
     * @param  array<array{sku: string, brand?: string|null, category_l1_id?: int|null}> $orderItems
     * @return OrderSensitivityResult
     */
    public function analyzeOrder(array $orderItems): OrderSensitivityResult
    {
        $itemResults = $this->identifyBatch($orderItems);

        $hasSensitive = false;
        foreach ($itemResults as $result) {
            if ($result->isSensitive) {
                $hasSensitive = true;
                break;
            }
        }

        if ($hasSensitive) {
            Log::info('[SensitiveGoodsService] Order contains sensitive items, applying all_safe strategy.', [
                'item_count'     => count($orderItems),
                'sensitive_count' => count(array_filter($itemResults, fn (SensitivityResult $r) => $r->isSensitive)),
            ]);

            return OrderSensitivityResult::sensitive($itemResults);
        }

        return OrderSensitivityResult::normal($itemResults);
    }

    /* ================================================================
     |  批量判定
     | ================================================================ */

    /**
     * 批量识别多个商品
     *
     * 优化：一次性加载所有 sensitive_brands 和 categories，避免 N+1 查询。
     *
     * @param  array<array{sku: string, brand?: string|null, category_l1_id?: int|null}> $items
     * @return array<SensitivityResult>
     */
    public function identifyBatch(array $items): array
    {
        // 预加载品牌黑名单（利用缓存）
        $this->getSensitiveBrands();

        // 预加载涉及的 L1 品类
        $categoryIds = array_filter(
            array_unique(array_column($items, 'category_l1_id')),
            fn ($id) => $id !== null
        );
        if (!empty($categoryIds)) {
            $this->preloadCategories($categoryIds);
        }

        $results = [];
        foreach ($items as $item) {
            $results[] = $this->identify(
                sku:          $item['sku'],
                brandName:    $item['brand'] ?? null,
                categoryL1Id: $item['category_l1_id'] ?? null,
            );
        }

        return $results;
    }

    /* ================================================================
     |  缓存管理
     | ================================================================ */

    /**
     * 清除品牌黑名单缓存
     */
    public function clearCache(): void
    {
        Cache::forget(self::CACHE_KEY_BRANDS);
        $this->cachedBrands = null;
        $this->categoryCache = [];

        Log::info('[SensitiveGoodsService] Cache cleared.');
    }

    /* ================================================================
     |  Level 1: SKU 前缀匹配
     | ================================================================ */

    /**
     * 检查 SKU 是否匹配敏感前缀列表
     */
    private function matchSkuPrefix(string $sku): ?SensitivityResult
    {
        $upperSku = strtoupper($sku);
        $prefixes = $this->getSensitivePrefixes();

        foreach ($prefixes as $prefix) {
            if (Str::startsWith($upperSku, strtoupper($prefix))) {
                return SensitivityResult::sensitive('sku', 100, "SKU prefix: {$prefix}");
            }
        }

        return null;
    }

    /**
     * 获取敏感 SKU 前缀列表
     *
     * 优先从 config('product-sync.sensitive_prefixes') 读取，
     * 不存在则使用默认常量。
     *
     * @return array<string>
     */
    private function getSensitivePrefixes(): array
    {
        /** @var array<string>|null $configured */
        $configured = config('product-sync.sensitive.sku_prefixes');

        return $configured ?? self::DEFAULT_SENSITIVE_PREFIXES;
    }

    /* ================================================================
     |  Level 2: 品牌黑名单匹配
     | ================================================================ */

    /** @var Collection<SensitiveBrand>|null 内存缓存 */
    private ?Collection $cachedBrands = null;

    /**
     * 检查品牌名是否命中黑名单
     */
    private function matchBrand(string $brandName, ?int $categoryL1Id): ?SensitivityResult
    {
        $brands = $this->getSensitiveBrands();

        foreach ($brands as $brand) {
            // 品类过滤：品牌绑定了品类时，仅匹配对应品类
            if ($brand->category_l1_id !== null && $categoryL1Id !== null && $brand->category_l1_id !== $categoryL1Id) {
                continue;
            }

            if ($brand->matchesBrand($brandName)) {
                return SensitivityResult::sensitive(
                    'brand',
                    90,
                    "Brand: {$brand->brand_name} (risk: {$brand->risk_level})"
                );
            }
        }

        return null;
    }

    /**
     * 获取敏感品牌列表（Redis 缓存 30min + 内存缓存）
     *
     * @return Collection<SensitiveBrand>
     */
    private function getSensitiveBrands(): Collection
    {
        if ($this->cachedBrands !== null) {
            return $this->cachedBrands;
        }

        $this->cachedBrands = Cache::remember(
            self::CACHE_KEY_BRANDS,
            self::CACHE_TTL,
            fn () => SensitiveBrand::active()->get()
        );

        return $this->cachedBrands;
    }

    /* ================================================================
     |  Level 3: 品类敏感度判定
     | ================================================================ */

    /** @var array<int, ProductCategoryL1> 品类内存缓存 */
    private array $categoryCache = [];

    /**
     * 检查品类敏感度
     *
     * 条件：is_sensitive = true 且 sensitive_ratio >= 50
     */
    private function matchCategory(int $categoryL1Id): ?SensitivityResult
    {
        $category = $this->getCategory($categoryL1Id);

        if ($category === null) {
            return null;
        }

        if ($category->is_sensitive && (float) $category->sensitive_ratio >= 50.0) {
            $ratio = (int) round((float) $category->sensitive_ratio);

            return SensitivityResult::sensitive(
                'category',
                $ratio,
                "Category: {$category->code} (sensitive_ratio: {$category->sensitive_ratio}%)"
            );
        }

        return null;
    }

    /**
     * 获取品类（带内存缓存）
     */
    private function getCategory(int $categoryL1Id): ?ProductCategoryL1
    {
        if (isset($this->categoryCache[$categoryL1Id])) {
            return $this->categoryCache[$categoryL1Id];
        }

        $category = ProductCategoryL1::find($categoryL1Id);

        if ($category !== null) {
            $this->categoryCache[$categoryL1Id] = $category;
        }

        return $category;
    }

    /**
     * 预加载品类到内存缓存
     *
     * @param  array<int> $categoryIds
     */
    private function preloadCategories(array $categoryIds): void
    {
        $missing = array_diff($categoryIds, array_keys($this->categoryCache));

        if (empty($missing)) {
            return;
        }

        $categories = ProductCategoryL1::whereIn('id', $missing)->get();

        foreach ($categories as $category) {
            $this->categoryCache[$category->id] = $category;
        }
    }
}
