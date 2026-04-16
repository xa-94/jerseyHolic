<?php

namespace App\Services\Payment;

use App\DTOs\SafeDescriptionDTO;
use App\Models\Central\PaypalSafeDescription;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * 商品描述脱敏服务（M3-010 / F-PAY-070~073）
 *
 * 三层防护策略，将仿牌商品描述映射为 PayPal/Stripe 可接受的安全名称。
 *
 * 优先级：
 *  Layer 2（最高） — 站点级模板：store_id = 具体值 且 product_category 匹配
 *  Layer 1（次优） — 全局品类映射：store_id IS NULL 且 product_category 匹配
 *  Layer 3（兜底） — 硬编码默认："General Merchandise"
 *
 * 在候选列表中采用加权随机选取算法，防止固定模式被识别。
 * 候选列表使用 Redis 缓存（30 分钟 TTL），每次仍执行随机选取。
 */
class SafeDescriptionService
{
    /** 缓存 Key 前缀 */
    private const CACHE_PREFIX = 'paypal_safe_desc';

    /** 缓存 TTL（秒） */
    private const CACHE_TTL = 1800; // 30 分钟

    /** 兜底默认安全描述 */
    private const DEFAULT_SAFE_NAME        = 'General Merchandise';
    private const DEFAULT_SAFE_DESCRIPTION = 'Quality products';
    private const DEFAULT_CATEGORY_CODE    = '5999';

    /* ----------------------------------------------------------------
     |  核心方法
     | ---------------------------------------------------------------- */

    /**
     * 解析安全商品描述（三层防护）
     *
     * @param  int|null $storeId          站点 ID（null 表示仅查全局）
     * @param  string   $productCategory  商品分类标识
     * @return SafeDescriptionDTO
     */
    public function resolve(?int $storeId, string $productCategory): SafeDescriptionDTO
    {
        // Layer 2：站点级模板（最高优先级）
        if ($storeId !== null) {
            $storeCandidates = $this->getCandidates($storeId, $productCategory);
            if ($storeCandidates->isNotEmpty()) {
                return $this->weightedRandomSelect($storeCandidates);
            }
        }

        // Layer 1：全局品类映射
        $globalCandidates = $this->getCandidates(null, $productCategory);
        if ($globalCandidates->isNotEmpty()) {
            return $this->weightedRandomSelect($globalCandidates);
        }

        // Layer 3：硬编码兜底
        Log::info('[SafeDescriptionService] Fallback to default safe description.', [
            'store_id' => $storeId,
            'category' => $productCategory,
        ]);

        return new SafeDescriptionDTO(
            self::DEFAULT_SAFE_NAME,
            self::DEFAULT_SAFE_DESCRIPTION,
            self::DEFAULT_CATEGORY_CODE,
        );
    }

    /**
     * 清除指定 store_id + category 的缓存
     *
     * 管理员更新/删除安全描述时调用。
     *
     * @param  int|null $storeId
     * @param  string   $productCategory
     * @return void
     */
    public function clearCache(?int $storeId, string $productCategory): void
    {
        $key = $this->buildCacheKey($storeId, $productCategory);
        Cache::forget($key);
    }

    /**
     * 批量清除缓存（更新时同时清除站点级和全局级）
     *
     * @param  int|null $storeId
     * @param  string   $productCategory
     * @return void
     */
    public function clearRelatedCaches(?int $storeId, string $productCategory): void
    {
        // 清除精确缓存
        $this->clearCache($storeId, $productCategory);

        // 若是站点级规则，也清除全局级缓存（因全局可能受影响）
        if ($storeId !== null) {
            $this->clearCache(null, $productCategory);
        }
    }

    /* ----------------------------------------------------------------
     |  私有方法
     | ---------------------------------------------------------------- */

    /**
     * 获取候选安全描述列表（带缓存）
     *
     * @param  int|null $storeId
     * @param  string   $productCategory
     * @return Collection<PaypalSafeDescription>
     */
    private function getCandidates(?int $storeId, string $productCategory): Collection
    {
        $key = $this->buildCacheKey($storeId, $productCategory);

        return Cache::remember($key, self::CACHE_TTL, function () use ($storeId, $productCategory) {
            $query = PaypalSafeDescription::query()
                ->enabled()
                ->where('product_category', $productCategory);

            if ($storeId !== null) {
                $query->where('store_id', $storeId);
            } else {
                $query->whereNull('store_id');
            }

            return $query->orderByDesc('weight')->get();
        });
    }

    /**
     * 加权随机选取算法（防模式识别）
     *
     * 根据权重值随机选取一条安全描述，权重越高被选中概率越大。
     * P(i) = weight(i) / Σweight
     *
     * @param  Collection<PaypalSafeDescription> $descriptions  非空候选列表
     * @return SafeDescriptionDTO
     */
    private function weightedRandomSelect(Collection $descriptions): SafeDescriptionDTO
    {
        $totalWeight = $descriptions->sum('weight');
        $random      = mt_rand(1, $totalWeight);
        $cumulative  = 0;

        foreach ($descriptions as $desc) {
            $cumulative += $desc->weight;
            if ($random <= $cumulative) {
                return SafeDescriptionDTO::fromModel($desc);
            }
        }

        // 理论上不会到达此处，兜底返回最后一条
        return SafeDescriptionDTO::fromModel($descriptions->last());
    }

    /**
     * 构建缓存 Key
     *
     * @param  int|null $storeId
     * @param  string   $category
     * @return string
     */
    private function buildCacheKey(?int $storeId, string $category): string
    {
        $storeKey = $storeId ?? 'global';
        return self::CACHE_PREFIX . ":{$storeKey}:{$category}";
    }
}
