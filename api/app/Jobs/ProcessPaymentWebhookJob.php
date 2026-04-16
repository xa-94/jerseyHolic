<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Services\Payment\PayPalWebhookHandler;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * 支付 Webhook 异步处理 Job（M3-008）
 *
 * Webhook 入口验签后立即 dispatch 此 Job，异步处理具体事件逻辑。
 * 根据 gateway 参数分发到对应 Handler（PayPal / Stripe）。
 *
 * 队列：payment（最高优先级）
 * 重试：3 次，间隔 [30s, 120s, 300s]
 */
class ProcessPaymentWebhookJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /** 最大重试次数 */
    public int $tries = 3;

    /** 退避间隔（秒）：第1次30s → 第2次120s → 第3次300s */
    public array $backoff = [30, 120, 300];

    /** 任务超时（秒） */
    public int $timeout = 120;

    /**
     * @param string $gateway   支付网关标识（paypal / stripe）
     * @param string $eventType 事件类型
     * @param array  $payload   完整 Webhook 请求体
     * @param int    $storeId   站点 ID
     */
    public function __construct(
        private readonly string $gateway,
        private readonly string $eventType,
        private readonly array  $payload,
        private readonly int    $storeId = 0,
    ) {
        $this->onQueue('payment');
    }

    /**
     * 执行 Webhook 事件处理
     */
    public function handle(): void
    {
        Log::info('[ProcessPaymentWebhookJob] Processing webhook', [
            'gateway'    => $this->gateway,
            'event_type' => $this->eventType,
            'event_id'   => $this->payload['id'] ?? '',
            'store_id'   => $this->storeId,
            'attempt'    => $this->attempts(),
        ]);

        match ($this->gateway) {
            'paypal' => $this->handlePayPal(),
            'stripe' => $this->handleStripe(),
            default  => Log::warning('[ProcessPaymentWebhookJob] Unknown gateway', [
                'gateway' => $this->gateway,
            ]),
        };
    }

    /**
     * PayPal 事件处理
     */
    private function handlePayPal(): void
    {
        /** @var PayPalWebhookHandler $handler */
        $handler = app(PayPalWebhookHandler::class);
        $handler->handle($this->payload, $this->storeId);
    }

    /**
     * Stripe 事件处理（预留）
     *
     * TODO: Batch 5B 实现 StripeWebhookHandler
     */
    private function handleStripe(): void
    {
        Log::info('[ProcessPaymentWebhookJob] Stripe handler not yet implemented', [
            'event_type' => $this->eventType,
        ]);
    }

    /**
     * 任务失败回调
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('[ProcessPaymentWebhookJob] Job failed after all retries', [
            'gateway'    => $this->gateway,
            'event_type' => $this->eventType,
            'event_id'   => $this->payload['id'] ?? '',
            'store_id'   => $this->storeId,
            'error'      => $exception->getMessage(),
            'trace'      => $exception->getTraceAsString(),
        ]);
    }
}
