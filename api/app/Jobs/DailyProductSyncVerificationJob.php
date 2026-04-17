<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\Central\Merchant;
use App\Models\Merchant\SyncRule;
use App\Services\MerchantDatabaseService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * 每日商品同步校验 Job
 *
 * 定时检查所有 auto_sync 规则，对超过同步间隔的 merchant+store 组合
 * 分发增量同步任务，确保商品数据一致性。
 *
 * 调度注册（在 Batch 6 的 Kernel.php 中）：
 *   $schedule->job(new DailyProductSyncVerificationJob)->dailyAt('03:00');
 */
class DailyProductSyncVerificationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * 最大重试次数
     */
    public int $tries = 1;

    /**
     * 超时时间（秒）— 1 小时
     */
    public int $timeout = 3600;

    public function __construct()
    {
        $this->onQueue('product-sync');
    }

    /**
     * Execute the job.
     */
    public function handle(MerchantDatabaseService $merchantDb): void
    {
        Log::info('[DailyProductSyncVerification] Starting daily sync verification.');

        $merchants = Merchant::where('status', 1)->get();
        $totalChecked   = 0;
        $totalDispatched = 0;

        foreach ($merchants as $merchant) {
            try {
                $dispatched = $this->checkMerchant($merchant, $merchantDb);
                $totalChecked++;
                $totalDispatched += $dispatched;
            } catch (\Throwable $e) {
                Log::error('[DailyProductSyncVerification] Failed to check merchant.', [
                    'merchant_id' => $merchant->id,
                    'error'       => $e->getMessage(),
                ]);
            }
        }

        Log::info('[DailyProductSyncVerification] Completed.', [
            'merchants_checked' => $totalChecked,
            'jobs_dispatched'   => $totalDispatched,
        ]);
    }

    /**
     * 检查单个商户的 auto_sync 规则，按需分发增量同步
     *
     * @return int 分发的任务数
     */
    protected function checkMerchant(Merchant $merchant, MerchantDatabaseService $merchantDb): int
    {
        $dispatched = 0;

        // 在商户数据库上下文中查询 auto_sync 规则
        $autoSyncRules = $merchantDb->run($merchant, function () {
            return SyncRule::enabled()
                ->autoSync()
                ->get();
        });

        if ($autoSyncRules->isEmpty()) {
            return 0;
        }

        foreach ($autoSyncRules as $rule) {
            $intervalHours = $rule->sync_interval_hours ?? 24;
            $lastSyncedAt  = $rule->last_synced_at;

            // 检查是否超过同步间隔
            $needsSync = $lastSyncedAt === null
                || $lastSyncedAt->diffInHours(now()) >= $intervalHours;

            if (!$needsSync) {
                Log::debug('[DailyProductSyncVerification] Rule still within interval, skipping.', [
                    'merchant_id'   => $merchant->id,
                    'sync_rule_id'  => $rule->id,
                    'last_synced_at' => $lastSyncedAt?->toIso8601String(),
                    'interval_hours' => $intervalHours,
                ]);
                continue;
            }

            $targetStoreIds = $rule->target_store_ids ?? [];
            $excludedStoreIds = $rule->excluded_store_ids ?? [];

            foreach ($targetStoreIds as $storeId) {
                if (in_array($storeId, $excludedStoreIds, true)) {
                    continue;
                }

                BatchSyncProductsJob::dispatch(
                    merchantId: $merchant->id,
                    storeId:    (int) $storeId,
                    type:       'incremental',
                    since:      $lastSyncedAt?->toIso8601String(),
                    syncRuleId: $rule->id,
                );

                $dispatched++;

                Log::info('[DailyProductSyncVerification] Incremental sync dispatched.', [
                    'merchant_id'  => $merchant->id,
                    'store_id'     => $storeId,
                    'sync_rule_id' => $rule->id,
                    'since'        => $lastSyncedAt?->toIso8601String(),
                ]);
            }
        }

        return $dispatched;
    }
}
