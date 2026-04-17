<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\Central\Merchant;
use App\Models\Central\Store;
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
 * 异步批量商品同步 Job
 *
 * 支持全量同步（full）和增量同步（incremental）两种模式。
 * 调用 ProductSyncService 的 fullSync / incrementalSync 方法执行。
 */
class BatchSyncProductsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * 最大重试次数
     */
    public int $tries = 2;

    /**
     * 超时时间（秒）— 全量同步可能耗时较长
     */
    public int $timeout = 600;

    /**
     * 重试间隔（秒）
     *
     * @var int[]
     */
    public array $backoff = [120, 300];

    /**
     * @param int         $merchantId  商户 ID
     * @param int         $storeId     目标 Store ID
     * @param string      $type        同步类型: 'full' | 'incremental'
     * @param string|null $since       增量同步起始时间（ISO8601 字符串，nullable）
     * @param int|null    $syncRuleId  同步规则 ID（nullable）
     */
    public function __construct(
        protected readonly int $merchantId,
        protected readonly int $storeId,
        protected readonly string $type = 'full',
        protected readonly ?string $since = null,
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
        Log::info('[BatchSyncProductsJob] Starting.', [
            'merchant_id' => $this->merchantId,
            'store_id'    => $this->storeId,
            'type'        => $this->type,
            'since'       => $this->since,
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

        $result = match ($this->type) {
            'incremental' => $syncService->incrementalSync(
                $merchant,
                $store,
                $syncRule,
                $this->since ? \Carbon\Carbon::parse($this->since) : null,
            ),
            default => $syncService->fullSync($merchant, $store, $syncRule),
        };

        Log::info('[BatchSyncProductsJob] Completed.', [
            'merchant_id' => $this->merchantId,
            'store_id'    => $this->storeId,
            'type'        => $this->type,
            'total'       => $result->total,
            'succeeded'   => $result->succeeded,
            'failed'      => $result->failed,
            'skipped'     => $result->skipped,
            'duration'    => $result->duration,
        ]);
    }

    /**
     * Handle a job failure.
     */
    public function failed(?\Throwable $exception): void
    {
        Log::error('[BatchSyncProductsJob] Job failed permanently.', [
            'merchant_id' => $this->merchantId,
            'store_id'    => $this->storeId,
            'type'        => $this->type,
            'error'       => $exception?->getMessage(),
            'attempts'    => $this->attempts(),
        ]);
    }
}
