<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\DTOs\ElectionResult;
use App\Enums\ErrorCode;
use App\Exceptions\BusinessException;
use App\Jobs\ProcessPaymentWebhookJob;
use App\Services\Payment\ElectionService;
use App\Services\Payment\PayPalGateway;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * 买家端支付控制器（M3-008）
 *
 * 路由：
 *  POST /api/v1/store/payment/create          — 创建支付订单
 *  POST /api/v1/store/payment/capture/{orderNo} — 捕获支付
 *  POST /api/v1/webhooks/paypal               — PayPal Webhook 入口
 */
class PaymentController extends BaseApiController
{
    public function __construct(
        private readonly ElectionService $electionService,
        private readonly PayPalGateway   $payPalGateway,
    ) {}

    /**
     * 创建支付订单
     *
     * 调用 ElectionService 选号 → 根据选号结果分发到对应 Gateway → 返回支付链接。
     * 支持 PayPal 和 Stripe（Stripe 后续 Batch 5B 实现）。
     */
    public function create(Request $request): JsonResponse
    {
        $request->validate([
            'order_no'         => 'required|string|max:64',
            'amount'           => 'required|numeric|min:0.01',
            'currency'         => 'sometimes|string|max:3',
            'payment_method'   => 'required|string|in:paypal,stripe,credit_card',
            'store_id'         => 'required|integer|min:1',
            'product_category' => 'sometimes|string|max:64',
            'return_url'       => 'sometimes|url|max:500',
            'cancel_url'       => 'sometimes|url|max:500',
            'items'            => 'sometimes|array',
            'items.*.name'     => 'required_with:items|string|max:255',
            'items.*.quantity' => 'required_with:items|integer|min:1',
            'items.*.price'    => 'required_with:items|numeric|min:0',
        ], [
            'order_no.required'       => '订单号不能为空',
            'amount.required'         => '支付金额不能为空',
            'amount.min'              => '支付金额必须大于 0',
            'payment_method.required' => '支付方式不能为空',
            'payment_method.in'       => '不支持的支付方式',
            'store_id.required'       => '站点ID不能为空',
        ]);

        $storeId       = (int) $request->input('store_id');
        $paymentMethod = $request->input('payment_method');
        $amount        = $request->input('amount');
        $orderNo       = $request->input('order_no');

        // 买家信息（用于选号风控）
        $buyerInfo = [
            'ip'                 => $request->ip(),
            'email'              => $request->user()?->email ?? '',
            'device_fingerprint' => $request->header('X-Device-Fingerprint', ''),
        ];

        Log::info('[PaymentController] Creating payment', [
            'order_no'       => $orderNo,
            'amount'         => $amount,
            'payment_method' => $paymentMethod,
            'store_id'       => $storeId,
        ]);

        // Step 1：选号
        $election = $this->electionService->elect($storeId, $paymentMethod, (string) $amount, $buyerInfo);

        if (!$election->success) {
            Log::warning('[PaymentController] Election failed', [
                'order_no' => $orderNo,
                'code'     => $election->code,
                'message'  => $election->message,
            ]);
            return $this->error(
                ErrorCode::PAYMENT_ERROR->value,
                '支付账号选择失败: ' . $election->message,
            );
        }

        $account = $election->account;

        // Step 2：根据支付方式分发到对应 Gateway
        $orderData = [
            'store_id'         => $storeId,
            'product_category' => $request->input('product_category', 'default'),
            'order_no'         => $orderNo,
            'amount'           => (string) $amount,
            'currency'         => $request->input('currency', 'USD'),
            'return_url'       => $request->input('return_url'),
            'cancel_url'       => $request->input('cancel_url'),
            'items'            => $request->input('items', []),
        ];

        try {
            $result = match ($account->pay_method) {
                'paypal'      => $this->payPalGateway->createOrder($orderData, $account),
                // 'stripe'   => $this->stripeGateway->createOrder($orderData, $account),
                default       => throw new BusinessException(ErrorCode::PAYMENT_ERROR, "不支持的支付方式: {$account->pay_method}"),
            };
        } catch (BusinessException $e) {
            throw $e;
        } catch (\Throwable $e) {
            Log::error('[PaymentController] Gateway createOrder failed', [
                'order_no'   => $orderNo,
                'account_id' => $account->id,
                'error'      => $e->getMessage(),
            ]);
            return $this->error(ErrorCode::PAYMENT_ERROR->value, '支付订单创建失败');
        }

        // Step 3：标记账号使用
        $this->electionService->markAccountUsed($account);

        return $this->success([
            'order_no'        => $orderNo,
            'gateway_order_id' => $result['order_id'] ?? '',
            'approve_url'     => $result['approve_url'] ?? '',
            'status'          => $result['status'] ?? '',
            'payment_method'  => $account->pay_method,
            'account_id'      => $account->id,
        ], '支付订单创建成功');
    }

    /**
     * 捕获（扣款）已批准的支付订单
     *
     * 前端完成支付授权后调用此接口完成扣款。
     */
    public function capture(Request $request, string $orderNo): JsonResponse
    {
        $request->validate([
            'gateway_order_id' => 'required|string|max:128',
            'account_id'       => 'required|integer|min:1',
            'payment_method'   => 'required|string|in:paypal,stripe',
        ], [
            'gateway_order_id.required' => '网关订单ID不能为空',
            'account_id.required'       => '支付账号ID不能为空',
        ]);

        $gatewayOrderId = $request->input('gateway_order_id');
        $accountId      = (int) $request->input('account_id');
        $paymentMethod  = $request->input('payment_method');

        $account = \App\Models\Central\PaymentAccount::findOrFail($accountId);

        Log::info('[PaymentController] Capturing payment', [
            'order_no'         => $orderNo,
            'gateway_order_id' => $gatewayOrderId,
            'account_id'       => $accountId,
            'payment_method'   => $paymentMethod,
        ]);

        try {
            $result = match ($paymentMethod) {
                'paypal' => $this->payPalGateway->captureOrder($gatewayOrderId, $account),
                default  => throw new BusinessException(ErrorCode::PAYMENT_ERROR, "不支持的支付方式: {$paymentMethod}"),
            };
        } catch (BusinessException $e) {
            throw $e;
        } catch (\Throwable $e) {
            Log::error('[PaymentController] Gateway captureOrder failed', [
                'order_no'         => $orderNo,
                'gateway_order_id' => $gatewayOrderId,
                'error'            => $e->getMessage(),
            ]);
            return $this->error(ErrorCode::PAYMENT_ERROR->value, '支付捕获失败');
        }

        return $this->success([
            'order_no'   => $orderNo,
            'capture_id' => $result['capture_id'] ?? '',
            'status'     => $result['status'] ?? '',
            'amount'     => $result['amount'] ?? '',
            'currency'   => $result['currency'] ?? '',
        ], '支付捕获成功');
    }

    /**
     * PayPal Webhook 入口
     *
     * 中间件 VerifyPayPalWebhook 已完成签名验证。
     * 此处仅做幂等判断后 dispatch 异步 Job 处理。
     */
    public function paypalWebhook(Request $request): JsonResponse
    {
        $payload   = $request->all();
        $eventId   = $payload['id'] ?? '';
        $eventType = $payload['event_type'] ?? '';

        // 从payload中提取 storeId（通过 purchase_units custom_id 传递）
        $resource       = $payload['resource'] ?? [];
        $purchaseUnits  = $resource['purchase_units'] ?? [];
        $storeId        = (int) ($purchaseUnits[0]['custom_id'] ?? $resource['custom_id'] ?? 0);

        Log::info('[PaymentController] PayPal webhook received', [
            'event_id'   => $eventId,
            'event_type' => $eventType,
            'store_id'   => $storeId,
        ]);

        // 异步处理：dispatch 到 payment 队列
        ProcessPaymentWebhookJob::dispatch(
            gateway:   'paypal',
            eventType: $eventType,
            payload:   $payload,
            storeId:   $storeId,
        );

        // Webhook 必须快速返回 200
        return response()->json([
            'code'    => 0,
            'message' => 'Webhook received',
            'data'    => null,
        ]);
    }
}
