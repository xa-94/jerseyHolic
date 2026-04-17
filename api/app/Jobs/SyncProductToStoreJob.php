<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\Central\Merchant;
use App\Models\Central\Store;
use App\Models\Merchant\MasterProduct;
use App\Models\Merchant\SyncRule;
use App\Services\MerchantDatabaseService;
use App\Services\Product\ProductSyncService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * 异步单商品同步 Job
 *
 * 将单个 MasterProduct 同步到指定 Store 的 Tenant DB。
 * 调用 ProductSyncService::syncToStore() 执行实际同步逻辑。
 *
 * 失败时自动将 MasterProduct.sync_status 标记为 failed。
 */
class SyncProductToStoreJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * 最大重试次数
     */
    public int $tries = 3;

    /**
     * 超时时间（秒）
     */
    public int $timeout = 120;

    /**
     * 重试间隔（秒），递增策略
     *
     * @var int[]
     */
    public array $backoff = [60, 120, 300];

    /**
     * @param int      $merchantId       商户 ID
     * @param int      $masterProductId  MasterProduct ID
     * @param int      $storeId          目标 Store ID
     * @param int|null $syncRuleId       同步规则 ID（nullable）
     */
    public function __construct(
        protected readonly int $merchantId,
        protected readonly int $masterProductId,
        protected readonly int $storeId,
        protected readonly ?int $syncRuleId = null,
    ) {
        $this->onQueue('product-sync');
    }

    /**
     * Execute the job.
     */
    public function handle(
        ProductSyncService $syncService,
        MerchantDatabaseService $merchantDb,
    ): void {
        Log::info('[SyncProductToStoreJob] Starting.', [
            'merchant_id'       => $this->merchantId,
            'master_product_id' => $this->masterProductId,
            'store_id'          => $this->storeId,
            'sync_rule_id'      => $this->syncRuleId,
        ]);

        $merchant = Merchant::findOrFail($this->merchantId);
        $store    = Store::findOrFail($this->storeId);

        // 加载可选的 SyncRule
        $syncRule = null;
        if ($this->syncRuleId !== null) {
            $syncRule = $merchantDb->run($merchant, function () {
                return SyncRule::find($this->syncRuleId);
            });
        }

        $result = $syncService->syncToStore($merchant, $this->masterProductId, $store, $syncRule);

        if ($result->success) {
            Log::info('[SyncProductToStoreJob] Completed successfully.', [
                'master_product_id' => $this->masterProductId,
                'tenant_product_id' => $result->tenantProductId,
            ]);
        } else {
            Log::warning('[SyncProductToStoreJob] Sync returned failure.', [
                'master_product_id' => $this->masterProductId,
                'errors'            => $result->errors,
            ]);
        }
    }

    /**
     * Handle a job failure (final attempt exhausted).
     *
     * 将 MasterProduct 的 sync_status 标记为 failed。
     */
    public function failed(?\Throwable $exception): void
    {
        Log::error('[SyncProductToStoreJob] Job failed permanently.', [
            'merchant_id'       => $this->merchantId,
            'master_product_id' => $this->masterProductId,
            'store_id'          => $this->storeId,
            'error'             => $exception?->getMessage(),
            'attempts'          => $this->attempts(),
        ]);

        try {
            $merchant  = Merchant::find($this->merchantId);
            if ($merchant) {
                $merchantDb = app(MerchantDatabaseService::class);
                $merchantDb->run($merchant, function () {
                    MasterProduct::where('id', $this->masterProductId)
                        ->update(['sync_status' => MasterProduct::SYNC_FAILED]);
                });
            }
        } catch (\Throwable $e) {
            Log::error('[SyncProductToStoreJob] Failed to update sync_status.', [
                'error' => $e->getMessage(),
            ]);
        }
    }
}
