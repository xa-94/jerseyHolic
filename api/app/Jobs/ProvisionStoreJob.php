<?php

namespace App\Jobs;

use App\Events\StoreProvisionFailed;
use App\Exceptions\StoreProvisioningException;
use App\Models\Central\Merchant;
use App\Models\Central\Store;
use App\Services\StoreProvisioningService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * 异步站点创建 Job
 *
 * 将 StoreProvisioningService::provision() 放入队列执行，
 * 适用于耗时较长的批量创建场景。
 *
 * 失败时自动更新 Store 状态为 provisioning_failed（-1）。
 * 最多重试 3 次，间隔递增（10s, 30s, 60s）。
 */
class ProvisionStoreJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * 最大重试次数
     */
    public int $tries = 3;

    /**
     * 超时时间（秒）— 站点创建可能涉及 DB 创建 + 迁移
     */
    public int $timeout = 300;

    /**
     * 重试间隔（秒），递增策略
     *
     * @var int[]
     */
    public array $backoff = [10, 30, 60];

    /**
     * 商户 ID
     */
    protected int $merchantId;

    /**
     * 站点配置数据
     *
     * @var array<string, mixed>
     */
    protected array $storeData;

    /**
     * @param int   $merchantId 所属商户 ID
     * @param array $storeData  站点配置数据（同 StoreProvisioningService::provision 的 $data 参数）
     */
    public function __construct(int $merchantId, array $storeData)
    {
        $this->merchantId = $merchantId;
        $this->storeData  = $storeData;

        $this->onQueue('provisioning');
    }

    /**
     * Execute the job.
     */
    public function handle(StoreProvisioningService $service): void
    {
        Log::info('[ProvisionStoreJob] Starting.', [
            'merchant_id' => $this->merchantId,
            'store_name'  => $this->storeData['store_name'] ?? null,
        ]);

        $merchant = Merchant::findOrFail($this->merchantId);

        $store = $service->provision($merchant, $this->storeData);

        Log::info('[ProvisionStoreJob] Completed successfully.', [
            'store_id'    => $store->id,
            'merchant_id' => $this->merchantId,
        ]);
    }

    /**
     * Handle a job failure.
     *
     * 最终失败时记录日志并触发失败事件。
     */
    public function failed(?\Throwable $exception): void
    {
        Log::error('[ProvisionStoreJob] Job failed permanently.', [
            'merchant_id' => $this->merchantId,
            'store_data'  => $this->storeData,
            'error'       => $exception?->getMessage(),
            'attempts'    => $this->attempts(),
        ]);

        // 尝试找到可能已部分创建的 Store 并标记失败
        $storeCode = $this->storeData['store_code'] ?? null;
        if ($storeCode) {
            $store = Store::where('store_code', $storeCode)
                ->where('merchant_id', $this->merchantId)
                ->first();

            if ($store) {
                $store->update(['status' => -1]); // provisioning_failed
            }
        }

        // 触发失败事件
        event(new StoreProvisionFailed(null, $exception ?? new \RuntimeException('Unknown error'), [
            'merchant_id' => $this->merchantId,
            'store_data'  => $this->storeData,
        ]));
    }
}
