<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\BaseController;
use App\Services\Payment\StripeWebhookHandler;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Stripe Webhook 控制器
 *
 * 接收并处理 Stripe 发送的 Webhook 事件通知。
 * 路由：POST /api/v1/webhooks/stripe
 * 中间件：VerifyStripeWebhook（签名验证）
 *
 * 注意：此控制器独立于 PaymentController（Task #109），
 * 仅处理 Stripe Webhook 事件，不涉及支付创建流程。
 */
class StripeWebhookController extends BaseController
{
    public function __construct(
        private readonly StripeWebhookHandler $webhookHandler,
    ) {}

    /**
     * 处理 Stripe Webhook 事件
     *
     * Stripe 要求 Webhook 端点在 5 秒内返回 2xx，
     * 否则会进行重试（最多 3 天内重试数十次）。
     *
     * @param  Request $request
     * @return JsonResponse
     */
    public function handle(Request $request): JsonResponse
    {
        $payload = $request->json()->all();

        if (empty($payload)) {
            Log::warning('[StripeWebhookController] Empty payload received.');
            return $this->error(40000, 'Empty payload');
        }

        $eventType = $payload['type'] ?? 'unknown';
        $eventId   = $payload['id'] ?? 'unknown';

        Log::info('[StripeWebhookController] Webhook received.', [
            'event_id'   => $eventId,
            'event_type' => $eventType,
        ]);

        try {
            $result = $this->webhookHandler->handle($payload);

            return $this->success($result);
        } catch (\Exception $e) {
            Log::error('[StripeWebhookController] Webhook processing error.', [
                'event_id'   => $eventId,
                'event_type' => $eventType,
                'error'      => $e->getMessage(),
            ]);

            // 返回 500 让 Stripe 重试
            return response()->json([
                'code'    => 50000,
                'message' => 'Internal processing error',
                'data'    => null,
            ], 500);
        }
    }
}
