<?php

namespace App\Jobs;

use App\Services\Payment\SettlementService;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * 结算单自动生成 Job（月结）
 *
 * 定时触发：每月 1 日凌晨 2:00（由 Scheduler 调度）
 * 结算周期：默认上月 1 日 00:00 ~ 上月末 23:59:59
 * 队列：report（第 5 优先级）
 *
 * 用法：
 *  - 全部商户：GenerateSettlementJob::dispatch()
 *  - 指定商户：GenerateSettlementJob::dispatch(merchantId: 42)
 *  - 自定义周期：GenerateSettlementJob::dispatch(periodStart: '2025-01-01', periodEnd: '2025-01-31')
 */
class GenerateSettlementJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /** 最大重试次数 */
    public int $tries = 3;

    /** 重试间隔（秒）— 5 分钟 */
    public int $backoff = 300;

    /** 任务超时（秒）— 10 分钟 */
    public int $timeout = 600;

    /**
     * @param int|null    $merchantId  指定商户 ID（null = 全部活跃商户）
     * @param string|null $periodStart 结算周期开始（Y-m-d，默认上月首日）
     * @param string|null $periodEnd   结算周期结束（Y-m-d，默认上月末日）
     */
    public function __construct(
        private readonly ?int    $merchantId  = null,
        private readonly ?string $periodStart = null,
        private readonly ?string $periodEnd   = null,
    ) {
        $this->onQueue('report');
    }

    /**
     * 执行结算单生成
     */
    public function handle(SettlementService $service): void
    {
        $start = $this->periodStart
            ? Carbon::parse($this->periodStart)->startOfDay()
            : now()->subMonth()->startOfMonth();

        $end = $this->periodEnd
            ? Carbon::parse($this->periodEnd)->endOfDay()
            : now()->subMonth()->endOfMonth();

        Log::info('[GenerateSettlementJob] Starting settlement generation.', [
            'merchant_id'  => $this->merchantId ?? 'all',
            'period_start' => $start->toDateString(),
            'period_end'   => $end->toDateString(),
        ]);

        if ($this->merchantId !== null) {
            $record = $service->generateForMerchant($this->merchantId, $start, $end);

            Log::info('[GenerateSettlementJob] Single merchant settlement completed.', [
                'settlement_id' => $record->id,
                'merchant_id'   => $this->merchantId,
                'net_amount'    => $record->net_amount,
            ]);
        } else {
            $records = $service->generateForAllMerchants($start, $end);

            Log::info('[GenerateSettlementJob] Batch settlement completed.', [
                'total_generated' => $records->count(),
            ]);
        }
    }

    /**
     * 任务失败回调
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('[GenerateSettlementJob] Job failed after all retries.', [
            'merchant_id'  => $this->merchantId ?? 'all',
            'period_start' => $this->periodStart,
            'period_end'   => $this->periodEnd,
            'error'        => $exception->getMessage(),
        ]);
    }
}
