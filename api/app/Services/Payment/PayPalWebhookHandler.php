<?php

declare(strict_types=1);

namespace App\Services\Payment;

use App\Enums\ErrorCode;
use App\Exceptions\BusinessException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * PayPal Webhook 事件处理器（M3-008）
 *
 * 处理 PayPal 推送的 Webhook 事件：
 *  - PAYMENT.CAPTURE.COMPLETED → 更新订单支付状态为 paid
 *  - PAYMENT.CAPTURE.DENIED    → 标记支付失败
 *  - CUSTOMER.DISPUTE.CREATED  → 创建争议记录 + 通知管理员
 *
 * 幂等性保障：event_id + store_id 组合唯一键，Redis SETNX 72h TTL。
 */
class PayPalWebhookHandler
{
    /** 幂等缓存 Key 前缀 */
    private const IDEMPOTENT_PREFIX = 'paypal_webhook';

    /** 幂等缓存 TTL（秒）— 72 小时 */
    private const IDEMPOTENT_TTL = 259200;

    /* ================================================================
     |  公开 API
     | ================================================================ */

    /**
     * 处理 PayPal Webhook 事件
     *
     * @param  array $payload 完整的 Webhook 请求体
     * @param  int   $storeId 当前站点 ID（从路由或上下文获取）
     * @return bool  是否实际处理（false = 已处理过 / 不支持的事件）
     */
    public function handle(array $payload, int $storeId = 0): bool
    {
        $eventId   = $payload['id'] ?? '';
        $eventType = $payload['event_type'] ?? '';

        if ($eventId === '' || $eventType === '') {
            Log::warning('[PayPalWebhook] Missing event_id or event_type', [
                'payload_keys' => array_keys($payload),
            ]);
            return false;
        }

        // 幂等检查
        if ($this->isDuplicate($eventId, $storeId)) {
            Log::info('[PayPalWebhook] Duplicate event skipped', [
                'event_id'   => $eventId,
                'event_type' => $eventType,
                'store_id'   => $storeId,
            ]);
            return false;
        }

        Log::info('[PayPalWebhook] Processing event', [
            'event_id'   => $eventId,
            'event_type' => $eventType,
            'store_id'   => $storeId,
        ]);

        return match ($eventType) {
            'PAYMENT.CAPTURE.COMPLETED' => $this->handleCaptureCompleted($payload, $storeId),
            'PAYMENT.CAPTURE.DENIED'    => $this->handleCaptureDenied($payload, $storeId),
            'CUSTOMER.DISPUTE.CREATED'  => $this->handleDisputeCreated($payload, $storeId),
            default => $this->handleUnknownEvent($eventType, $eventId),
        };
    }

    /* ================================================================
     |  事件处理方法
     | ================================================================ */

    /**
     * PAYMENT.CAPTURE.COMPLETED — 支付捕获成功
     *
     * 更新订单支付状态为 paid，记录 capture_id 和实际支付金额。
     */
    private function handleCaptureCompleted(array $payload, int $storeId): bool
    {
        $resource  = $payload['resource'] ?? [];
        $captureId = $resource['id'] ?? '';
        $amount    = $resource['amount']['value'] ?? '0.00';
        $currency  = $resource['amount']['currency_code'] ?? 'USD';

        // 通过 supplementary_data 或 custom_id 获取系统订单号
        $orderNo = $resource['custom_id']
            ?? $resource['invoice_id']
            ?? $this->extractOrderNo($payload);

        Log::info('[PayPalWebhook] Capture completed', [
            'capture_id' => $captureId,
            'order_no'   => $orderNo,
            'amount'     => $amount,
            'currency'   => $currency,
            'store_id'   => $storeId,
        ]);

        if ($orderNo === '') {
            Log::error('[PayPalWebhook] Cannot identify order from capture completed event', [
                'capture_id' => $captureId,
                'resource'   => $resource,
            ]);
            return false;
        }

        try {
            DB::table('orders')
                ->where('order_no', $orderNo)
                ->update([
                    'pay_status'       => 'paid',
                    'payment_id'       => $captureId,
                    'paid_amount'      => $amount,
                    'paid_currency'    => $currency,
                    'paid_at'          => now(),
                    'updated_at'       => now(),
                ]);

            Log::info('[PayPalWebhook] Order marked as paid', [
                'order_no'   => $orderNo,
                'capture_id' => $captureId,
            ]);

            return true;
        } catch (\Throwable $e) {
            Log::error('[PayPalWebhook] Failed to update order status', [
                'order_no' => $orderNo,
                'error'    => $e->getMessage(),
            ]);
            throw new BusinessException(ErrorCode::PAYMENT_ERROR, '更新订单支付状态失败: ' . $e->getMessage());
        }
    }

    /**
     * PAYMENT.CAPTURE.DENIED — 支付捕获被拒
     *
     * 标记订单支付失败。
     */
    private function handleCaptureDenied(array $payload, int $storeId): bool
    {
        $resource  = $payload['resource'] ?? [];
        $captureId = $resource['id'] ?? '';
        $orderNo   = $resource['custom_id']
            ?? $resource['invoice_id']
            ?? $this->extractOrderNo($payload);

        Log::warning('[PayPalWebhook] Capture denied', [
            'capture_id' => $captureId,
            'order_no'   => $orderNo,
            'store_id'   => $storeId,
        ]);

        if ($orderNo === '') {
            Log::error('[PayPalWebhook] Cannot identify order from capture denied event', [
                'capture_id' => $captureId,
            ]);
            return false;
        }

        DB::table('orders')
            ->where('order_no', $orderNo)
            ->update([
                'pay_status'  => 'failed',
                'error_msg'   => 'PayPal capture denied',
                'updated_at'  => now(),
            ]);

        return true;
    }

    /**
     * CUSTOMER.DISPUTE.CREATED — 客户发起争议
     *
     * 创建争议记录并通知管理员。
     */
    private function handleDisputeCreated(array $payload, int $storeId): bool
    {
        $resource  = $payload['resource'] ?? [];
        $disputeId = $resource['dispute_id'] ?? '';
        $reason    = $resource['reason'] ?? 'UNKNOWN';
        $status    = $resource['status'] ?? '';
        $amount    = $resource['dispute_amount']['value'] ?? '0.00';
        $currency  = $resource['dispute_amount']['currency_code'] ?? 'USD';

        // 从 disputed_transactions 中提取关联信息
        $transactions = $resource['disputed_transactions'] ?? [];
        $buyerEmail   = $transactions[0]['buyer']['email'] ?? '';

        Log::warning('[PayPalWebhook] Dispute created', [
            'dispute_id'  => $disputeId,
            'reason'      => $reason,
            'amount'      => $amount,
            'store_id'    => $storeId,
            'buyer_email' => $buyerEmail,
        ]);

        // 记录争议到数据库
        DB::table('payment_disputes')->insert([
            'dispute_id'     => $disputeId,
            'gateway'        => 'paypal',
            'store_id'       => $storeId,
            'reason'         => $reason,
            'status'         => $status,
            'amount'         => $amount,
            'currency'       => $currency,
            'buyer_email'    => $buyerEmail,
            'raw_payload'    => json_encode($resource),
            'created_at'     => now(),
            'updated_at'     => now(),
        ]);

        // 通知管理员（如果 NotificationService 可用）
        $this->notifyAdmin($disputeId, $reason, $amount, $currency, $storeId);

        return true;
    }

    /**
     * 未知事件类型 — 仅记录日志
     */
    private function handleUnknownEvent(string $eventType, string $eventId): bool
    {
        Log::info('[PayPalWebhook] Unhandled event type', [
            'event_type' => $eventType,
            'event_id'   => $eventId,
        ]);
        return false;
    }

    /* ================================================================
     |  幂等性
     | ================================================================ */

    /**
     * 检查事件是否已处理（SETNX 原子操作）
     *
     * @param  string $eventId
     * @param  int    $storeId
     * @return bool   true = 重复事件
     */
    private function isDuplicate(string $eventId, int $storeId): bool
    {
        $key = self::IDEMPOTENT_PREFIX . ":{$eventId}:{$storeId}";

        // Redis SETNX：不存在则设置并返回 true（首次处理），已存在返回 false（重复）
        $isNew = Cache::add($key, 1, self::IDEMPOTENT_TTL);

        return !$isNew;
    }

    /* ================================================================
     |  辅助方法
     | ================================================================ */

    /**
     * 从 Webhook payload 中提取系统订单号
     *
     * 尝试从 purchase_units.reference_id 或 supplementary_data 获取。
     */
    private function extractOrderNo(array $payload): string
    {
        // 尝试从 resource.supplementary_data
        $supplementary = $payload['resource']['supplementary_data'] ?? [];
        if (!empty($supplementary['related_ids']['order_id'])) {
            // 通过 PayPal order ID 反查
            return '';
        }

        // 尝试从 purchase_units
        $purchaseUnits = $payload['resource']['purchase_units'] ?? [];
        if (!empty($purchaseUnits[0]['reference_id'])) {
            return $purchaseUnits[0]['reference_id'];
        }

        return '';
    }

    /**
     * 通知管理员争议事件
     */
    private function notifyAdmin(string $disputeId, string $reason, string $amount, string $currency, int $storeId): void
    {
        try {
            // 尝试使用 NotificationService
            $notificationService = app(\App\Services\NotificationService::class);
            $notificationService->sendToAdmin(
                title: '⚠️ PayPal 争议提醒',
                content: "争议ID: {$disputeId}\n原因: {$reason}\n金额: {$currency} {$amount}\n站点: {$storeId}",
                level: 'warning',
            );
        } catch (\Throwable $e) {
            // NotificationService 不可用时仅记录日志
            Log::warning('[PayPalWebhook] Failed to notify admin about dispute', [
                'dispute_id' => $disputeId,
                'error'      => $e->getMessage(),
            ]);
        }
    }
}
