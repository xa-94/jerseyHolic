<?php

namespace App\Services\Payment;

use App\Models\Central\MerchantPaymentGroupMapping;
use App\Models\Central\PaymentAccount;
use App\Models\Central\PaymentAccountGroup;
use App\Models\Central\Store;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\ValidationException;

/**
 * 支付分组映射服务
 *
 * 负责 Domain→Store→Merchant→PaymentAccountGroup 三层映射查询，
 * 以及商户-支付分组映射的 CRUD 操作。
 *
 * 缓存策略：
 *  - Key:  payment_mapping:{merchant_id}:{pay_method}
 *  - TTL:  3600s（1 小时）
 *  - 清除: 映射 CRUD 操作后立即失效
 */
class PaymentGroupMappingService
{
    /** 缓存前缀 */
    private const CACHE_PREFIX = 'payment_mapping';

    /** 缓存 TTL（秒） */
    private const CACHE_TTL = 3600;

    /* ----------------------------------------------------------------
     |  三层映射查询
     | ---------------------------------------------------------------- */

    /**
     * 根据当前租户上下文和支付方式，查找对应的支付账号分组
     *
     * 查询链路：
     *   Store → Merchant → MerchantPaymentGroupMapping → PaymentAccountGroup
     *
     * @param  int    $storeId       当前站点 ID
     * @param  string $paymentMethod 支付方式（paypal/stripe/credit_card/antom）
     * @return PaymentAccountGroup|null 匹配的支付分组，无匹配返回 null
     */
    public function resolveGroup(int $storeId, string $paymentMethod): ?PaymentAccountGroup
    {
        $store = Store::find($storeId);

        if (!$store || !$store->merchant_id) {
            return null;
        }

        $merchantId = $store->merchant_id;
        $cacheKey   = $this->buildCacheKey($merchantId, $paymentMethod);

        return Cache::remember($cacheKey, self::CACHE_TTL, function () use ($merchantId, $paymentMethod) {
            $mapping = MerchantPaymentGroupMapping::where('merchant_id', $merchantId)
                ->where('pay_method', $paymentMethod)
                ->orderByDesc('priority')
                ->first();

            if (!$mapping) {
                return null;
            }

            return $mapping->paymentGroup;
        });
    }

    /* ----------------------------------------------------------------
     |  映射 CRUD
     | ---------------------------------------------------------------- */

    /**
     * 获取商户的所有支付分组映射
     *
     * @param  int $merchantId
     * @return Collection<int, MerchantPaymentGroupMapping>
     */
    public function getMerchantMappings(int $merchantId): Collection
    {
        return MerchantPaymentGroupMapping::with('paymentGroup')
            ->where('merchant_id', $merchantId)
            ->orderByDesc('priority')
            ->get();
    }

    /**
     * 为商户设置支付分组映射
     *
     * @param  int    $merchantId    商户 ID
     * @param  string $paymentMethod 支付方式
     * @param  int    $groupId       支付账号分组 ID
     * @param  int    $priority      优先级（数值越大越优先）
     * @return MerchantPaymentGroupMapping
     *
     * @throws ValidationException 同一 merchant_id + pay_method 已存在时
     */
    public function setMapping(
        int    $merchantId,
        string $paymentMethod,
        int    $groupId,
        int    $priority = 0,
    ): MerchantPaymentGroupMapping {
        // 唯一约束检查
        $exists = MerchantPaymentGroupMapping::where('merchant_id', $merchantId)
            ->where('pay_method', $paymentMethod)
            ->exists();

        if ($exists) {
            throw ValidationException::withMessages([
                'pay_method' => ["该商户已配置 {$paymentMethod} 支付分组映射，请勿重复创建"],
            ]);
        }

        // 确认分组存在
        PaymentAccountGroup::findOrFail($groupId);

        $mapping = MerchantPaymentGroupMapping::create([
            'merchant_id'      => $merchantId,
            'pay_method'       => $paymentMethod,
            'payment_group_id' => $groupId,
            'priority'         => $priority,
        ]);

        $this->clearCache($merchantId, $paymentMethod);

        return $mapping->load('paymentGroup');
    }

    /**
     * 更新映射
     *
     * @param  int   $mappingId
     * @param  array $data  可更新字段：payment_group_id, priority
     * @return MerchantPaymentGroupMapping
     */
    public function updateMapping(int $mappingId, array $data): MerchantPaymentGroupMapping
    {
        $mapping = MerchantPaymentGroupMapping::findOrFail($mappingId);

        // 若变更分组，确认新分组存在
        if (isset($data['payment_group_id'])) {
            PaymentAccountGroup::findOrFail($data['payment_group_id']);
        }

        $mapping->update($data);

        $this->clearCache($mapping->merchant_id, $mapping->pay_method);

        return $mapping->fresh()->load('paymentGroup');
    }

    /**
     * 删除映射
     *
     * @param  int $mappingId
     * @return bool
     */
    public function deleteMapping(int $mappingId): bool
    {
        $mapping = MerchantPaymentGroupMapping::findOrFail($mappingId);

        $merchantId    = $mapping->merchant_id;
        $paymentMethod = $mapping->pay_method;

        $result = (bool) $mapping->delete();

        $this->clearCache($merchantId, $paymentMethod);

        return $result;
    }

    /* ----------------------------------------------------------------
     |  可用账号查询
     | ---------------------------------------------------------------- */

    /**
     * 获取分组下的可用支付账号
     *
     * 筛选条件：status = active(1) 且 health_score >= 阈值
     *
     * @param  int $groupId        支付账号分组 ID
     * @param  int $minHealthScore 最低健康分（默认 30）
     * @return Collection<int, PaymentAccount>
     */
    public function getAvailableAccounts(int $groupId, int $minHealthScore = 30): Collection
    {
        $group = PaymentAccountGroup::findOrFail($groupId);

        // 根据分组的支付方式类型，选择对应关联字段
        $query = PaymentAccount::query()
            ->where(function ($q) use ($groupId) {
                $q->where('category_id', $groupId)
                  ->orWhere('cc_category_id', $groupId);
            })
            ->where('status', 1)
            ->where('health_score', '>=', $minHealthScore)
            ->orderByDesc('priority')
            ->orderByDesc('health_score');

        return $query->get();
    }

    /* ----------------------------------------------------------------
     |  缓存辅助
     | ---------------------------------------------------------------- */

    /**
     * 构建缓存 Key
     */
    private function buildCacheKey(int $merchantId, string $paymentMethod): string
    {
        return self::CACHE_PREFIX . ":{$merchantId}:{$paymentMethod}";
    }

    /**
     * 清除指定映射的缓存
     */
    private function clearCache(int $merchantId, string $paymentMethod): void
    {
        Cache::forget($this->buildCacheKey($merchantId, $paymentMethod));
    }
}
