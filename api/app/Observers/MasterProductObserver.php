<?php

declare(strict_types=1);

namespace App\Observers;

use App\Jobs\SyncProductToStoreJob;
use App\Models\Central\Merchant;
use App\Models\Merchant\MasterProduct;
use App\Models\Merchant\SyncRule;
use App\Services\MerchantDatabaseService;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;

/**
 * MasterProduct 模型观察者（自动触发同步）
 *
 * 当 MasterProduct 创建或关键字段更新时，
 * 检查是否存在 auto_sync=true 的 SyncRule，
 * 若有则自动分发 SyncProductToStoreJob 到相关 Store。
 *
 * 注册方式（在 Batch 6 的 ServiceProvider 中）：
 *   MasterProduct::observe(MasterProductObserver::class);
 */
class MasterProductObserver
{
    /**
     * 触发自动同步的关键字段
     *
     * 只有这些字段变更时才会触发自动同步。
     */
    protected const SYNC_TRIGGER_FIELDS = [
        'name',
        'description',
        'base_price',
        'images',
        'attributes',
        'variants',
        'status',
    ];

    public function __construct(
        protected readonly MerchantDatabaseService $merchantDb,
    ) {}

    /**
     * 商品创建后：检查 auto_sync 规则并分发同步任务
     */
    public function created(MasterProduct $masterProduct): void
    {
        $this->dispatchAutoSync($masterProduct);
    }

    /**
     * 商品更新后：仅在关键字段变更时触发自动同步
     */
    public function updated(MasterProduct $masterProduct): void
    {
        // 只在关键字段变更时触发
        if (!$masterProduct->isDirty(self::SYNC_TRIGGER_FIELDS)) {
            return;
        }

        $this->dispatchAutoSync($masterProduct);
    }

    /* ================================================================
     |  内部逻辑
     | ================================================================ */

    /**
     * 查找 auto_sync 规则并为每个目标 Store 分发同步 Job
     */
    protected function dispatchAutoSync(MasterProduct $masterProduct): void
    {
        try {
            // 从当前 merchant 连接的数据库名推断 merchant_id
            $merchantId = $this->resolveMerchantId();
            if ($merchantId === null) {
                Log::warning('[MasterProductObserver] Cannot resolve merchant_id, skipping auto sync.', [
                    'product_id' => $masterProduct->id,
                ]);
                return;
            }

            // 查询启用的 auto_sync 规则（在当前 merchant 连接下执行）
            $autoSyncRules = SyncRule::enabled()
                ->autoSync()
                ->get();

            if ($autoSyncRules->isEmpty()) {
                return;
            }

            foreach ($autoSyncRules as $rule) {
                $targetStoreIds = $rule->target_store_ids ?? [];
                $excludedStoreIds = $rule->excluded_store_ids ?? [];

                foreach ($targetStoreIds as $storeId) {
                    // 排除在排除列表中的 Store
                    if (in_array($storeId, $excludedStoreIds, true)) {
                        continue;
                    }

                    SyncProductToStoreJob::dispatch(
                        merchantId:      $merchantId,
                        masterProductId: $masterProduct->id,
                        storeId:         (int) $storeId,
                        syncRuleId:      $rule->id,
                    );

                    Log::info('[MasterProductObserver] Auto sync dispatched.', [
                        'merchant_id'       => $merchantId,
                        'master_product_id' => $masterProduct->id,
                        'store_id'          => $storeId,
                        'sync_rule_id'      => $rule->id,
                    ]);
                }
            }
        } catch (\Throwable $e) {
            // Observer 不应中断主流程，仅记录日志
            Log::error('[MasterProductObserver] Auto sync dispatch failed.', [
                'product_id' => $masterProduct->id,
                'error'      => $e->getMessage(),
            ]);
        }
    }

    /**
     * 从当前 merchant 数据库连接名推断 merchant_id
     *
     * merchant 数据库格式：jerseyholic_merchant_{id}
     */
    protected function resolveMerchantId(): ?int
    {
        $dbName = Config::get('database.connections.merchant.database');
        if (!$dbName) {
            return null;
        }

        $prefix = 'jerseyholic_merchant_';
        if (!str_starts_with($dbName, $prefix)) {
            return null;
        }

        $id = substr($dbName, strlen($prefix));

        return is_numeric($id) ? (int) $id : null;
    }
}
