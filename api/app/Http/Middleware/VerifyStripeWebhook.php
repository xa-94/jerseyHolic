<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/**
 * Stripe Webhook 签名验证中间件（M3-009）
 *
 * 验证 Stripe 发送的 Webhook 请求签名，确保请求来源合法。
 *
 * 验证流程：
 *  1. 检查 Stripe-Signature header 是否存在
 *  2. 解析 header 中的 t（timestamp）和 v1（signature）
 *  3. 验证 timestamp 是否在容差范围内（默认 300 秒）
 *  4. 使用 HMAC-SHA256 计算期望签名并比对
 *
 * 签名计算方式：
 *   expected = hash_hmac('sha256', "{timestamp}.{payload}", $webhookSecret)
 *
 * @see https://docs.stripe.com/webhooks/signatures
 */
class VerifyStripeWebhook
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $signatureHeader = $request->header('Stripe-Signature');

        if (empty($signatureHeader)) {
            Log::warning('[VerifyStripeWebhook] Missing Stripe-Signature header.');
            return $this->reject('Missing Stripe-Signature header');
        }

        // 解析 Stripe-Signature header
        $elements = $this->parseSignatureHeader($signatureHeader);

        if ($elements === null) {
            Log::warning('[VerifyStripeWebhook] Malformed Stripe-Signature header.', [
                'header' => $signatureHeader,
            ]);
            return $this->reject('Malformed Stripe-Signature header');
        }

        $timestamp  = $elements['timestamp'];
        $signatures = $elements['signatures'];

        // 时间容差检查（防重放攻击）
        $tolerance = (int) config('services.stripe.webhook_tolerance', 300);

        if (abs(time() - $timestamp) > $tolerance) {
            Log::warning('[VerifyStripeWebhook] Timestamp outside tolerance.', [
                'timestamp' => $timestamp,
                'tolerance' => $tolerance,
                'diff'      => abs(time() - $timestamp),
            ]);
            return $this->reject('Webhook timestamp expired');
        }

        // 获取 Webhook Secret
        $webhookSecret = config('services.stripe.webhook_secret');

        if (empty($webhookSecret)) {
            Log::error('[VerifyStripeWebhook] Webhook secret not configured.');
            return $this->reject('Webhook secret not configured');
        }

        // 计算期望签名
        $payload           = $request->getContent();
        $signedPayload     = $timestamp . '.' . $payload;
        $expectedSignature = hash_hmac('sha256', $signedPayload, $webhookSecret);

        // 验证签名（支持多个 v1 签名，任一匹配即可）
        $verified = false;
        foreach ($signatures as $sig) {
            if (hash_equals($expectedSignature, $sig)) {
                $verified = true;
                break;
            }
        }

        if (!$verified) {
            Log::warning('[VerifyStripeWebhook] Signature verification failed.', [
                'timestamp' => $timestamp,
            ]);
            return $this->reject('Invalid webhook signature');
        }

        return $next($request);
    }

    /* ----------------------------------------------------------------
     |  私有方法
     | ---------------------------------------------------------------- */

    /**
     * 解析 Stripe-Signature header
     *
     * 格式：t=1614556828,v1=abc123,v1=def456,...
     *
     * @param  string $header Stripe-Signature header 值
     * @return array{timestamp: int, signatures: string[]}|null
     */
    private function parseSignatureHeader(string $header): ?array
    {
        $items = explode(',', $header);

        $timestamp  = null;
        $signatures = [];

        foreach ($items as $item) {
            $parts = explode('=', trim($item), 2);

            if (count($parts) !== 2) {
                continue;
            }

            [$key, $value] = $parts;

            match ($key) {
                't'  => $timestamp = (int) $value,
                'v1' => $signatures[] = $value,
                default => null, // 忽略未知 key（如 v0）
            };
        }

        if ($timestamp === null || empty($signatures)) {
            return null;
        }

        return [
            'timestamp'  => $timestamp,
            'signatures' => $signatures,
        ];
    }

    /**
     * 返回 403 JSON 错误响应
     */
    private function reject(string $message): JsonResponse
    {
        return response()->json([
            'code'    => 403,
            'message' => $message,
            'data'    => null,
        ], 403);
    }
}
