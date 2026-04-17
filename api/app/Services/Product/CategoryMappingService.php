<?php

declare(strict_types=1);

namespace App\Services\Product;

use App\Models\Central\CategorySafeName;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * 品类级安全映射服务（M4-002 / F-PROD-030~033）
 *
 * 5 级优先级查询（从高到低）：
 *  Level 1（最高） — 站点覆盖：store_id = $storeId 的精确匹配
 *  Level 2         — 精确 SKU：sku_prefix 完全匹配 SKU 值
 *  Level 3         — SKU 前缀：sku_prefix 匹配 SKU 的前缀部分
 *  Level 4         — 品类级：category_l1_id + category_l2_id 匹配（全局，store_id=null）
 *  Level 5（兜底） — 仅 L1 匹配，或硬编码 "General Merchandise"
 *
 * 在每级候选列表中采用加权随机选取算法，防止固定模式被支付平台识别。
 * 候选列表使用 Redis 缓存（1 小时 TTL），每次从缓存列表中加权随机。
 */
class CategoryMappingService
{
    /** 缓存 Key 前缀 */
    private const CACHE_PREFIX = 'cat_safe_name';

    /** 缓存 TTL（秒） */
    private const CACHE_TTL = 3600; // 1 小时

    /** 兜底默认安全名称 */
    private const DEFAULT_SAFE_NAME = 'General Merchandise';

    /* ----------------------------------------------------------------
     |  核心方法
     | ---------------------------------------------------------------- */

    /**
     * 解析安全名称（5 级优先级）
     *
     * @param  int         $storeId       站点 ID
     * @param  string|null $sku           商品 SKU
     * @param  int|null    $categoryL1Id  L1 品类 ID
     * @param  int|null    $categoryL2Id  L2 品类 ID
     * @param  string      $locale        语言代码
     * @return string      安全名称
     */
    public function resolve(
        int $storeId,
        ?string $sku,
        ?int $categoryL1Id,
        ?int $categoryL2Id,
        string $locale = 'en',
    ): string {
        // Level 1: 站点覆盖（store_id 精确匹配 + 品类）
        if ($categoryL1Id !== null) {
            $candidates = $this->getCandidatesForStore($storeId, $categoryL1Id, $categoryL2Id);
            if ($candidates->isNotEmpty()) {
                return $this->weightedRandomSelect($candidates, $locale);
            }
        }

        // Level 2: 精确 SKU 匹配（sku_prefix = 完整 SKU）
        if ($sku !== null) {
            $candidates = $this->getCandidatesForSkuExact($sku);
            if ($candidates->isNotEmpty()) {
                return $this->weightedRandomSelect($candidates, $locale);
            }
        }

        // Level 3: SKU 前缀匹配
        if ($sku !== null) {
            $prefix = $this->extractSkuPrefix($sku);
            if ($prefix !== null) {
                $candidates = $this->getCandidatesForSkuPrefix($prefix);
                if ($candidates->isNotEmpty()) {
                    return $this->weightedRandomSelect($candidates, $locale);
                }
            }
        }

        // Level 4: 品类级匹配（L1 + L2，全局 store_id=null）
        if ($categoryL1Id !== null && $categoryL2Id !== null) {
            $candidates = $this->getCandidatesForCategory($categoryL1Id, $categoryL2Id);
            if ($candidates->isNotEmpty()) {
                return $this->weightedRandomSelect($candidates, $locale);
            }
        }

        // Level 5: 仅 L1 匹配（全局）
        if ($categoryL1Id !== null) {
            $candidates = $this->getCandidatesForL1Only($categoryL1Id);
            if ($candidates->isNotEmpty()) {
                return $this->weightedRandomSelect($candidates, $locale);
            }
        }

        // 硬编码兜底
        Log::info('[CategoryMappingService] Fallback to default safe name.', [
            'store_id'  => $storeId,
            'sku'       => $sku,
            'l1_id'     => $categoryL1Id,
            'l2_id'     => $categoryL2Id,
            'locale'    => $locale,
        ]);

        return self::DEFAULT_SAFE_NAME;
    }

    /**
     * 批量解析安全名称
     *
     * @param  int    $storeId   站点 ID
     * @param  array  $products  [['sku' => 'hic-001', 'category_l1_id' => 1, 'category_l2_id' => 2], ...]
     * @param  string $locale    语言代码
     * @return array  ['hic-001' => 'Athletic Training Jersey', ...]
     */
    public function resolveForProducts(int $storeId, array $products, string $locale = 'en'): array
    {
        $results = [];

        // 按品类分组，减少重复查询
        $grouped = $this->groupProductsByCategory($products);

        foreach ($grouped as $groupKey => $groupProducts) {
            // 取分组中第一个产品的品类信息做查询
            $first = $groupProducts[0];
            $l1Id = $first['category_l1_id'] ?? null;
            $l2Id = $first['category_l2_id'] ?? null;

            foreach ($groupProducts as $product) {
                $sku = $product['sku'] ?? null;
                $safeName = $this->resolve($storeId, $sku, $l1Id, $l2Id, $locale);
                if ($sku !== null) {
                    $results[$sku] = $safeName;
                }
            }
        }

        return $results;
    }

    /**
     * 清除缓存
     *
     * @param  int|null $storeId  传 null 清除所有缓存（使用 tag 前缀模式）
     */
    public function clearCache(?int $storeId = null): void
    {
        if ($storeId !== null) {
            // 清除指定站点相关缓存，使用通配符模式
            $pattern = self::CACHE_PREFIX . ":{$storeId}:*";
            $this->clearCacheByPattern($pattern);
        }

        // 始终清除全局缓存
        $pattern = self::CACHE_PREFIX . ':0:*';
        $this->clearCacheByPattern($pattern);

        // 清除 SKU 相关缓存
        $pattern = self::CACHE_PREFIX . ':sku:*';
        $this->clearCacheByPattern($pattern);

        Log::info('[CategoryMappingService] Cache cleared.', ['store_id' => $storeId]);
    }

    /* ----------------------------------------------------------------
     |  候选列表获取（带缓存）
     | ---------------------------------------------------------------- */

    /**
     * Level 1: 站点覆盖候选
     */
    private function getCandidatesForStore(int $storeId, int $l1Id, ?int $l2Id): Collection
    {
        $key = $this->buildCacheKey((string) $storeId, '', (string) $l1Id, (string) ($l2Id ?? 0));

        return Cache::remember($key, self::CACHE_TTL, function () use ($storeId, $l1Id, $l2Id): Collection {
            $query = CategorySafeName::query()
                ->active()
                ->where('store_id', $storeId)
                ->where('category_l1_id', $l1Id);

            if ($l2Id !== null) {
                $query->where(function ($q) use ($l2Id) {
                    $q->where('category_l2_id', $l2Id)
                      ->orWhereNull('category_l2_id');
                });
            }

            return $query->orderByDesc('weight')->get();
        });
    }

    /**
     * Level 2: 精确 SKU 匹配候选
     */
    private function getCandidatesForSkuExact(string $sku): Collection
    {
        $key = $this->buildCacheKey('0', $sku, '0', '0');

        return Cache::remember($key, self::CACHE_TTL, function () use ($sku): Collection {
            return CategorySafeName::query()
                ->active()
                ->whereNull('store_id')
                ->where('sku_prefix', $sku)
                ->orderByDesc('weight')
                ->get();
        });
    }

    /**
     * Level 3: SKU 前缀匹配候选
     */
    private function getCandidatesForSkuPrefix(string $prefix): Collection
    {
        $key = $this->buildCacheKey('0', "pfx:{$prefix}", '0', '0');

        return Cache::remember($key, self::CACHE_TTL, function () use ($prefix): Collection {
            return CategorySafeName::query()
                ->active()
                ->whereNull('store_id')
                ->where('sku_prefix', $prefix)
                ->orderByDesc('weight')
                ->get();
        });
    }

    /**
     * Level 4: 品类级候选（L1 + L2，全局）
     */
    private function getCandidatesForCategory(int $l1Id, int $l2Id): Collection
    {
        $key = $this->buildCacheKey('0', '', (string) $l1Id, (string) $l2Id);

        return Cache::remember($key, self::CACHE_TTL, function () use ($l1Id, $l2Id): Collection {
            return CategorySafeName::query()
                ->active()
                ->whereNull('store_id')
                ->whereNull('sku_prefix')
                ->where('category_l1_id', $l1Id)
                ->where('category_l2_id', $l2Id)
                ->orderByDesc('weight')
                ->get();
        });
    }

    /**
     * Level 5: 仅 L1 匹配候选（全局）
     */
    private function getCandidatesForL1Only(int $l1Id): Collection
    {
        $key = $this->buildCacheKey('0', '', (string) $l1Id, 'any');

        return Cache::remember($key, self::CACHE_TTL, function () use ($l1Id): Collection {
            return CategorySafeName::query()
                ->active()
                ->whereNull('store_id')
                ->whereNull('sku_prefix')
                ->where('category_l1_id', $l1Id)
                ->whereNull('category_l2_id')
                ->orderByDesc('weight')
                ->get();
        });
    }

    /* ----------------------------------------------------------------
     |  私有方法
     | ---------------------------------------------------------------- */

    /**
     * 加权随机选取算法（与 SafeDescriptionService 一致）
     *
     * P(i) = weight(i) / Σweight
     */
    private function weightedRandomSelect(Collection $candidates, string $locale): string
    {
        $totalWeight = $candidates->sum('weight');
        $random      = mt_rand(1, max(1, $totalWeight));
        $cumulative  = 0;

        foreach ($candidates as $item) {
            /** @var CategorySafeName $item */
            $cumulative += $item->weight;
            if ($random <= $cumulative) {
                return $item->getSafeName($locale);
            }
        }

        // 理论上不会到达此处，兜底返回最后一条
        return $candidates->last()->getSafeName($locale);
    }

    /**
     * 从 SKU 中提取前缀（取第一个 '-' 之前的部分）
     *
     * 示例：'hic-001' → 'hic', 'WPZ-A100' → 'WPZ', 'DIY123' → null
     */
    private function extractSkuPrefix(string $sku): ?string
    {
        $pos = strpos($sku, '-');
        if ($pos === false || $pos === 0) {
            return null;
        }

        return substr($sku, 0, $pos);
    }

    /**
     * 按品类分组产品
     *
     * @param  array $products
     * @return array<string, list<array>>
     */
    private function groupProductsByCategory(array $products): array
    {
        $grouped = [];

        foreach ($products as $product) {
            $l1 = $product['category_l1_id'] ?? 0;
            $l2 = $product['category_l2_id'] ?? 0;
            $key = "{$l1}:{$l2}";
            $grouped[$key][] = $product;
        }

        return $grouped;
    }

    /**
     * 构建缓存 Key
     */
    private function buildCacheKey(string $storeId, string $skuPrefix, string $l1Id, string $l2Id): string
    {
        $sku = $skuPrefix !== '' ? $skuPrefix : '_';
        return self::CACHE_PREFIX . ":{$storeId}:{$sku}:{$l1Id}:{$l2Id}";
    }

    /**
     * 按模式清除 Redis 缓存
     */
    private function clearCacheByPattern(string $pattern): void
    {
        try {
            /** @var \Illuminate\Redis\Connections\Connection $redis */
            $redis = Cache::getStore()->getRedis();
            $prefix = config('cache.prefix', '');
            $fullPattern = $prefix ? "{$prefix}:{$pattern}" : $pattern;

            $cursor = null;
            do {
                /** @var array $result */
                $result = $redis->scan($cursor, ['match' => $fullPattern, 'count' => 100]);
                if ($result === false) {
                    break;
                }
                [$cursor, $keys] = $result;
                if (!empty($keys)) {
                    $redis->del(...$keys);
                }
            } while ($cursor !== 0 && $cursor !== '0');
        } catch (\Throwable $e) {
            Log::warning('[CategoryMappingService] Failed to clear cache by pattern.', [
                'pattern' => $pattern,
                'error'   => $e->getMessage(),
            ]);
        }
    }
}
