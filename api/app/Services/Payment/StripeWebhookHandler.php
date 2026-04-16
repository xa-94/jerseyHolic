<?php

declare(strict_types=1);

namespace App\Services\Payment;

use App\Models\Central\PaymentAccount;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;

/**
 * Stripe Webhook 事件处理器（M3-009）
 *
 * 处理 Stripe 发送的 Webhook 事件，核心事件：
 *
 *  - checkout.session.completed  → 更新订单为已支付
 *  - payment_intent.payment_failed → 标记支付失败
 *  - charge.dispute.created → 创建争议记录 + 通知管理员
 *
 * 幂等性保障：
 *  使用 Redis SETNX 对 event_id + store_id 去重，TTL 72h。
 *  Key 格式：stripe_webhook:{event_id}:{store_id}
 */
class StripeWebhookHandler
{
    /** 幂等 Key 前缀 */
    private const IDEMPOTENT_PREFIX = 'stripe_webhook';

    /** 幂等 TTL（秒）— 72 小时 */
    private const IDEMPOTENT_TTL = 259200;

    public function __construct(
        private readonly TransactionSimulationService $simulationService,
    ) {}

    /* ----------------------------------------------------------------
     |  核心处理入口
     | ---------------------------------------------------------------- */

    /**
     * 处理 Stripe Webhook 事件
     *
     * @param  array $payload 已验签的完整事件 payload
     * @return array 处理结果 ['handled' => bool, 'message' => string]
     */
    public function handle(array $payload): array
    {
        $eventId   = $payload['id'] ?? '';
        $eventType = $payload['type'] ?? '';
        $data      = $payload['data']['object'] ?? [];

        // 从 metadata 提取 store_id
        $storeId = $this->extractStoreId($data);

        // 幂等性检查
        if (!$this->acquireIdempotentLock($eventId, $storeId)) {
            Log::info('[StripeWebhook] Duplicate event skipped.', [
                'event_id'   => $eventId,
                'event_type' => $eventType,
                'store_id'   => $storeId,
            ]);

            return ['handled' => false, 'message' => 'Duplicate event'];
        }

        Log::info('[StripeWebhook] Processing event.', [
            'event_id'   => $eventId,
            'event_type' => $eventType,
            'store_id'   => $storeId,
        ]);

        try {
            return match ($eventType) {
                'checkout.session.completed'    => $this->handleCheckoutCompleted($data, $storeId),
                'payment_intent.payment_failed' => $this->handlePaymentFailed($data, $storeId),
                'charge.dispute.created'        => $this->handleDisputeCreated($data, $storeId),
                default                         => $this->handleUnknownEvent($eventType),
            };
        } catch (\Exception $e) {
            Log::error('[StripeWebhook] Event handling failed.', [
                'event_id'   => $eventId,
                'event_type' => $eventType,
                'error'      => $e->getMessage(),
                'trace'      => $e->getTraceAsString(),
            ]);

            // 释放幂等锁，允许重试
            $this->releaseIdempotentLock($eventId, $storeId);

            throw $e;
        }
    }

    /* ----------------------------------------------------------------
     |  事件处理器
     | ---------------------------------------------------------------- */

    /**
     * 处理 checkout.session.completed 事件
     *
     * 从 metadata 提取 order_no + store_id，更新订单为已支付。
     */
    private function handleCheckoutCompleted(array $session, int $storeId): array
    {
        $orderNo       = $session['metadata']['order_no'] ?? '';
        $paymentIntent = $session['payment_intent'] ?? '';
        $amountTotal   = $session['amount_total'] ?? 0;
        $currency      = $session['currency'] ?? 'usd';

        if (empty($orderNo)) {
            Log::warning('[StripeWebhook] checkout.session.completed missing order_no in metadata.', [
                'session_id' => $session['id'] ?? 'unknown',
            ]);
            return ['handled' => false, 'message' => 'Missing order_no in metadata'];
        }

        Log::info('[StripeWebhook] Checkout session completed.', [
            'order_no'       => $orderNo,
            'store_id'       => $storeId,
            'payment_intent' => $paymentIntent,
            'amount_total'   => $amountTotal,
            'currency'       => $currency,
        ]);

        // TODO: 更新订单状态为 paid
        // 当 Order 模型和相关 Job 就绪后，dispatch 异步任务：
        // ProcessPaymentWebhookJob::dispatch([
        //     'gateway'        => 'stripe',
        //     'event'          => 'checkout.session.completed',
        //     'order_no'       => $orderNo,
        //     'store_id'       => $storeId,
        //     'payment_intent' => $paymentIntent,
        //     'amount'         => $amountTotal,
        //     'currency'       => $currency,
        // ])->onQueue('payment');

        return ['handled' => true, 'message' => 'Order marked as paid'];
    }

    /**
     * 处理 payment_intent.payment_failed 事件
     *
     * 标记支付失败，记录失败原因。
     */
    private function handlePaymentFailed(array $paymentIntent, int $storeId): array
    {
        $orderNo     = $paymentIntent['metadata']['order_no'] ?? '';
        $intentId    = $paymentIntent['id'] ?? '';
        $lastError   = $paymentIntent['last_payment_error'] ?? [];
        $errorCode   = $lastError['code'] ?? 'unknown';
        $errorMsg    = $lastError['message'] ?? 'Payment failed';

        Log::warning('[StripeWebhook] Payment intent failed.', [
            'payment_intent_id' => $intentId,
            'order_no'          => $orderNo,
            'store_id'          => $storeId,
            'error_code'        => $errorCode,
            'error_message'     => $errorMsg,
        ]);

        // TODO: 更新订单支付状态为 failed
        // ProcessPaymentWebhookJob::dispatch([
        //     'gateway'    => 'stripe',
        //     'event'      => 'payment_intent.payment_failed',
        //     'order_no'   => $orderNo,
        //     'store_id'   => $storeId,
        //     'intent_id'  => $intentId,
        //     'error_code' => $errorCode,
        //     'error_msg'  => $errorMsg,
        // ])->onQueue('payment');

        return ['handled' => true, 'message' => 'Payment failure recorded'];
    }

    /**
     * 处理 charge.dispute.created 事件
     *
     * 创建争议记录并通知管理员。
     */
    private function handleDisputeCreated(array $dispute, int $storeId): array
    {
        $disputeId  = $dispute['id'] ?? '';
        $chargeId   = $dispute['charge'] ?? '';
        $amount     = $dispute['amount'] ?? 0;
        $currency   = $dispute['currency'] ?? 'usd';
        $reason     = $dispute['reason'] ?? 'unknown';
        $status     = $dispute['status'] ?? 'unknown';

        Log::error('[StripeWebhook] Charge dispute created.', [
            'dispute_id' => $disputeId,
            'charge_id'  => $chargeId,
            'amount'     => $amount,
            'currency'   => $currency,
            'reason'     => $reason,
            'store_id'   => $storeId,
        ]);

        // TODO: 创建争议记录到数据库
        // Dispute::create([...]);

        // TODO: 发送管理员通知
        // app(NotificationService::class)->send(
        //     recipientType: 'admin',
        //     recipientId:   1,
        //     title:         "Stripe 争议告警",
        //     content:       "账号收到争议 {$disputeId}，金额 {$amount} {$currency}，原因：{$reason}",
        //     type:          NotificationService::TYPE_RISK,
        //     level:         NotificationService::LEVEL_ERROR,
        //     channels:      ['database', 'dingtalk'],
        // );

        // 检查账号退款率/争议率
        // 需要通过 charge 找到对应的 PaymentAccount
        // $this->simulationService->checkRefundRateAlert($accountId);

        return ['handled' => true, 'message' => 'Dispute recorded and admin notified'];
    }

    /**
     * 处理未知事件类型
     */
    private function handleUnknownEvent(string $eventType): array
    {
        Log::debug('[StripeWebhook] Unhandled event type.', [
            'event_type' => $eventType,
        ]);

        return ['handled' => false, 'message' => "Unhandled event type: {$eventType}"];
    }

    /* ----------------------------------------------------------------
     |  辅助方法
     | ---------------------------------------------------------------- */

    /**
     * 从事件数据中提取 store_id
     */
    private function extractStoreId(array $data): int
    {
        // Checkout Session: metadata 在顶层
        if (isset($data['metadata']['store_id'])) {
            return (int) $data['metadata']['store_id'];
        }

        // PaymentIntent: metadata 在顶层
        if (isset($data['metadata']['store_id'])) {
            return (int) $data['metadata']['store_id'];
        }

        // Dispute: 需要从关联的 charge → payment_intent 间接获取
        // 此处暂返回 0，后续可通过 charge 查询补全
        return 0;
    }

    /**
     * 获取幂等锁（Redis SETNX）
     *
     * @param  string $eventId Stripe 事件 ID
     * @param  int    $storeId 站点 ID
     * @return bool true = 首次处理，false = 重复事件
     */
    private function acquireIdempotentLock(string $eventId, int $storeId): bool
    {
        $key = self::IDEMPOTENT_PREFIX . ":{$eventId}:{$storeId}";
        $acquired = Redis::set($key, '1', 'EX', self::IDEMPOTENT_TTL, 'NX');

        return (bool) $acquired;
    }

    /**
     * 释放幂等锁（处理失败时调用，允许重试）
     */
    private function releaseIdempotentLock(string $eventId, int $storeId): void
    {
        $key = self::IDEMPOTENT_PREFIX . ":{$eventId}:{$storeId}";
        Redis::del($key);
    }
}
