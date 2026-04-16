<?php

namespace App\Services\Payment;

use App\Models\Central\Blacklist;
use App\Models\Central\Merchant;
use App\Services\NotificationService;
use Carbon\Carbon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * 黑名单管理服务（M3-018）
 *
 * 支持 4 维度风控拦截：IP / Email / 设备指纹 / 支付账号。
 * 查询使用 Redis 缓存（TTL 5 分钟），CRUD 后自动清除缓存。
 *
 * 集成场景：
 *  - ElectionService Layer 1：选号前多维度批量检查
 *  - MerchantRiskService：极高风险评分自动触发
 *  - Admin：手动管理
 */
class BlacklistService
{
    /** Redis 缓存 TTL（秒） */
    private const CACHE_TTL = 300;

    /** 缓存 Key 前缀 */
    private const CACHE_PREFIX = 'blacklist';

    public function __construct(
        private readonly NotificationService $notificationService,
    ) {}

    /* ----------------------------------------------------------------
     |  查询方法
     | ---------------------------------------------------------------- */

    /**
     * 检查是否在黑名单中
     *
     * @param  string $dimension  ip / email / device / payment_account
     * @param  string $value      要检查的值
     * @return bool
     */
    public function isBlocked(string $dimension, string $value): bool
    {
        $cacheKey = $this->buildCacheKey($dimension, $value);

        return Cache::remember($cacheKey, self::CACHE_TTL, function () use ($dimension, $value) {
            return Blacklist::query()
                ->match($dimension, $value)
                ->active()
                ->exists();
        });
    }

    /**
     * 批量检查多维度（选号算法 Layer 1 使用）
     *
     * @param  array $checks  ['ip' => '1.2.3.4', 'email' => 'test@test.com', ...]
     * @return array          ['ip' => false, 'email' => true, ...]
     */
    public function checkMultiple(array $checks): array
    {
        $results = [];

        foreach ($checks as $dimension => $value) {
            if (empty($value)) {
                $results[$dimension] = false;
                continue;
            }
            $results[$dimension] = $this->isBlocked($dimension, $value);
        }

        return $results;
    }

    /**
     * 获取黑名单列表（分页 + 筛选）
     *
     * @param  array $filters  可选 keys: dimension, scope, merchant_id, keyword, per_page
     * @param  int   $perPage
     * @return LengthAwarePaginator
     */
    public function list(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $query = Blacklist::query()->with('merchant:id,merchant_name');

        // 维度筛选
        if (!empty($filters['dimension'])) {
            $query->ofDimension($filters['dimension']);
        }

        // 作用范围筛选
        if (!empty($filters['scope'])) {
            if ($filters['scope'] === Blacklist::SCOPE_PLATFORM) {
                $query->platform();
            } elseif ($filters['scope'] === Blacklist::SCOPE_MERCHANT && !empty($filters['merchant_id'])) {
                $query->forMerchant((int) $filters['merchant_id']);
            }
        }

        // 商户 ID 筛选（不限 scope）
        if (!empty($filters['merchant_id']) && empty($filters['scope'])) {
            $query->where('merchant_id', (int) $filters['merchant_id']);
        }

        // 关键词搜索（value / reason）
        if (!empty($filters['keyword'])) {
            $kw = '%' . $filters['keyword'] . '%';
            $query->where(function ($q) use ($kw) {
                $q->where('value', 'like', $kw)
                  ->orWhere('reason', 'like', $kw);
            });
        }

        // 仅有效条目
        if (!empty($filters['active_only'])) {
            $query->active();
        }

        $perPage = (int) ($filters['per_page'] ?? $perPage);

        return $query->latest()->paginate($perPage);
    }

    /* ----------------------------------------------------------------
     |  写入方法
     | ---------------------------------------------------------------- */

    /**
     * 手动添加黑名单
     *
     * @param  string      $dimension   维度
     * @param  string      $value       值
     * @param  string      $reason      原因
     * @param  int|null    $merchantId  商户 ID（null 则为平台级）
     * @param  Carbon|null $expiresAt   过期时间（null 则永不过期）
     * @return Blacklist
     */
    public function add(
        string $dimension,
        string $value,
        string $reason,
        ?int $merchantId = null,
        ?Carbon $expiresAt = null,
    ): Blacklist {
        $scope = $merchantId !== null ? Blacklist::SCOPE_MERCHANT : Blacklist::SCOPE_PLATFORM;

        $entry = Blacklist::create([
            'scope'       => $scope,
            'merchant_id' => $merchantId,
            'dimension'   => $dimension,
            'value'       => $value,
            'reason'      => $reason,
            'expires_at'  => $expiresAt,
        ]);

        $this->clearCache($dimension, $value);

        Log::info('[Blacklist] Entry added', [
            'id'        => $entry->id,
            'dimension' => $dimension,
            'value'     => $value,
            'scope'     => $scope,
        ]);

        return $entry;
    }

    /**
     * 自动添加（风险评分触发）
     *
     * 将商户关联的邮箱和支付账号加入平台级黑名单。
     *
     * @param  int $merchantId
     * @return void
     */
    public function autoAdd(int $merchantId): void
    {
        $merchant = Merchant::findOrFail($merchantId);

        // 将商户邮箱加入黑名单
        $this->addIfNotExists(
            Blacklist::DIMENSION_EMAIL,
            $merchant->email,
            "风险评分自动触发 — 商户 #{$merchantId} 评分超过 80 阈值"
        );

        // 将商户下所有支付账号邮箱加入黑名单
        $merchant->load('stores.paymentAccounts');
        foreach ($merchant->stores as $store) {
            foreach ($store->paymentAccounts as $account) {
                if (!empty($account->email)) {
                    $this->addIfNotExists(
                        Blacklist::DIMENSION_PAYMENT_ACCOUNT,
                        $account->email,
                        "风险评分自动触发 — 商户 #{$merchantId} 关联支付账号"
                    );
                }
            }
        }

        $this->notificationService->sendToAdmin(
            '黑名单自动触发',
            "商户 #{$merchantId}（{$merchant->merchant_name}）因风险评分超过阈值已自动加入黑名单",
            NotificationService::TYPE_BLACKLIST,
            NotificationService::LEVEL_WARNING,
        );

        Log::warning('[Blacklist] Auto-added merchant to blacklist', [
            'merchant_id' => $merchantId,
        ]);
    }

    /**
     * 更新黑名单条目
     *
     * @param  int   $id
     * @param  array $data  可更新字段：reason, expires_at
     * @return Blacklist
     */
    public function update(int $id, array $data): Blacklist
    {
        $entry = Blacklist::findOrFail($id);

        $entry->update(array_intersect_key($data, array_flip(['reason', 'expires_at'])));
        $entry->refresh();

        $this->clearCache($entry->dimension, $entry->value);

        return $entry;
    }

    /**
     * 移除黑名单条目
     *
     * @param  int $id
     * @return bool
     */
    public function remove(int $id): bool
    {
        $entry = Blacklist::findOrFail($id);

        $this->clearCache($entry->dimension, $entry->value);

        Log::info('[Blacklist] Entry removed', [
            'id'        => $entry->id,
            'dimension' => $entry->dimension,
            'value'     => $entry->value,
        ]);

        return $entry->delete();
    }

    /* ----------------------------------------------------------------
     |  内部方法
     | ---------------------------------------------------------------- */

    /**
     * 如果不存在则添加（幂等）
     */
    private function addIfNotExists(string $dimension, string $value, string $reason): void
    {
        $exists = Blacklist::query()
            ->match($dimension, $value)
            ->active()
            ->exists();

        if (!$exists) {
            $this->add($dimension, $value, $reason);
        }
    }

    /**
     * 构建缓存 Key
     */
    private function buildCacheKey(string $dimension, string $value): string
    {
        return self::CACHE_PREFIX . ':' . $dimension . ':' . md5($value);
    }

    /**
     * 清除指定维度+值的缓存
     */
    private function clearCache(string $dimension, string $value): void
    {
        Cache::forget($this->buildCacheKey($dimension, $value));
    }
}
