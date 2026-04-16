<?php

declare(strict_types=1);

namespace App\Services\Payment;

use App\Services\Payment\PaymentGatewayInterface;
use App\Models\Central\PaymentAccount;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Stripe 支付网关服务（M3-009）
 *
 * 通过 Stripe Checkout Session 模式创建支付，集成要点：
 *
 *  - line_items 的商品名称使用 SafeDescriptionService 脱敏
 *  - 不传 images（防品牌暴露）
 *  - 金额经 TransactionSimulationService 微调后传入
 *  - metadata 携带 order_no + store_id 供 Webhook 回调使用
 *  - 认证方式：Secret Key（Bearer token）从 PaymentAccount credentials 获取
 *
 * 使用 Laravel HTTP Client（Guzzle）直接调用 Stripe REST API，
 * 不依赖 Stripe PHP SDK，降低外部依赖。
 */
class StripeGateway implements PaymentGatewayInterface
{
    /** Stripe API 基础地址 */
    private const BASE_URL = 'https://api.stripe.com';

    /** Stripe API 版本 */
    private const API_VERSION = '2023-10-16';

    public function __construct(
        private readonly SafeDescriptionService       $safeDescriptionService,
        private readonly TransactionSimulationService  $simulationService,
    ) {}

    /* ----------------------------------------------------------------
     |  PaymentGatewayInterface 实现
     | ---------------------------------------------------------------- */

    /**
     * 创建 Stripe Checkout Session
     *
     * 对应接口：POST /v1/checkout/sessions
     *
     * @param  array          $orderData 订单数据，需包含：
     *   - order_no:     string  订单号
     *   - amount:       string  订单金额（bcmath 精度字符串）
     *   - currency:     string  货币代码（如 USD）
     *   - store_id:     int     站点 ID
     *   - category:     string  商品分类标识
     *   - success_url:  string  支付成功回调 URL（可选，默认从 config 获取）
     *   - cancel_url:   string  取消支付回调 URL（可选，默认从 config 获取）
     * @param  PaymentAccount $account   支付账号（含 client_secret = Stripe Secret Key）
     * @return array 包含 id, url, status 等
     *
     * @throws \RuntimeException Stripe API 调用失败时抛出
     */
    public function createOrder(array $orderData, PaymentAccount $account): array
    {
        $orderNo  = $orderData['order_no'];
        $amount   = $orderData['amount'];
        $currency = strtolower($orderData['currency'] ?? 'usd');
        $storeId  = (int) ($orderData['store_id'] ?? 0);
        $category = $orderData['category'] ?? 'general';

        // 1. 获取安全商品描述
        $safeDesc = $this->safeDescriptionService->resolve($storeId, $category);

        // 2. 金额微调（防风控模式识别）
        $adjustedAmount = $this->simulationService->adjustAmount($amount, $currency);

        // 3. 转换为 Stripe 要求的最小单位（如 USD → cents）
        $amountInCents = $this->toSmallestUnit($adjustedAmount, $currency);

        // 4. 构建 success_url / cancel_url
        $successUrl = $orderData['success_url']
            ?? config('services.stripe.success_url', config('app.frontend_url') . '/payment/success');
        $cancelUrl  = $orderData['cancel_url']
            ?? config('services.stripe.cancel_url', config('app.frontend_url') . '/payment/cancel');

        // 5. 构建请求参数
        $params = [
            'mode'                   => 'payment',
            'success_url'            => $successUrl . '?session_id={CHECKOUT_SESSION_ID}',
            'cancel_url'             => $cancelUrl,
            'metadata[order_no]'     => $orderNo,
            'metadata[store_id]'     => (string) $storeId,
            'line_items[0][price_data][currency]'     => $currency,
            'line_items[0][price_data][unit_amount]'  => (string) $amountInCents,
            'line_items[0][price_data][product_data][name]'        => $safeDesc->safeName,
            'line_items[0][price_data][product_data][description]' => $safeDesc->safeDescription,
            'line_items[0][quantity]' => '1',
        ];

        Log::info('[StripeGateway] Creating Checkout Session.', [
            'order_no'        => $orderNo,
            'store_id'        => $storeId,
            'original_amount' => $amount,
            'adjusted_amount' => $adjustedAmount,
            'currency'        => $currency,
            'safe_name'       => $safeDesc->safeName,
        ]);

        // 6. 调用 Stripe API
        $response = $this->request('POST', '/v1/checkout/sessions', $params, $account);

        return [
            'id'           => $response['id'] ?? '',
            'url'          => $response['url'] ?? '',
            'status'       => $response['status'] ?? 'unknown',
            'payment_intent' => $response['payment_intent'] ?? null,
            'raw'          => $response,
        ];
    }

    /**
     * 捕获支付（Stripe Checkout Session 默认自动 capture）
     *
     * 若 PaymentIntent 设置为手动 capture，则调用此接口。
     * 对应接口：POST /v1/payment_intents/{id}/capture
     *
     * @param  string         $orderId  PaymentIntent ID（如 pi_xxx）
     * @param  PaymentAccount $account  支付账号
     * @return array 捕获结果
     */
    public function captureOrder(string $orderId, PaymentAccount $account): array
    {
        Log::info('[StripeGateway] Capturing PaymentIntent.', [
            'payment_intent_id' => $orderId,
        ]);

        $response = $this->request('POST', "/v1/payment_intents/{$orderId}/capture", [], $account);

        return [
            'id'     => $response['id'] ?? '',
            'status' => $response['status'] ?? 'unknown',
            'amount' => isset($response['amount']) ? $this->fromSmallestUnit((string) $response['amount'], $response['currency'] ?? 'usd') : '0.00',
            'raw'    => $response,
        ];
    }

    /**
     * 退款
     *
     * 对应接口：POST /v1/refunds
     *
     * @param  string         $captureId  PaymentIntent ID 或 Charge ID
     * @param  string         $amount     退款金额（bcmath 精度字符串）
     * @param  string         $currency   货币代码
     * @param  PaymentAccount $account    支付账号
     * @return array 退款结果
     */
    public function refundCapture(string $captureId, string $amount, string $currency, PaymentAccount $account): array
    {
        $amountInCents = $this->toSmallestUnit($amount, strtolower($currency));

        $params = [
            'amount' => (string) $amountInCents,
        ];

        // 自动判断传入的是 PaymentIntent ID 还是 Charge ID
        if (str_starts_with($captureId, 'pi_')) {
            $params['payment_intent'] = $captureId;
        } else {
            $params['charge'] = $captureId;
        }

        Log::info('[StripeGateway] Creating refund.', [
            'capture_id' => $captureId,
            'amount'     => $amount,
            'currency'   => $currency,
        ]);

        $response = $this->request('POST', '/v1/refunds', $params, $account);

        return [
            'id'     => $response['id'] ?? '',
            'status' => $response['status'] ?? 'unknown',
            'amount' => isset($response['amount']) ? $this->fromSmallestUnit((string) $response['amount'], strtolower($currency)) : '0.00',
            'raw'    => $response,
        ];
    }

    /**
     * 查询 Checkout Session 状态
     *
     * 对应接口：GET /v1/checkout/sessions/{id}
     *
     * @param  string         $orderId  Checkout Session ID（如 cs_xxx）
     * @param  PaymentAccount $account  支付账号
     * @return array 会话状态数据
     */
    public function getOrderStatus(string $orderId, PaymentAccount $account): array
    {
        Log::info('[StripeGateway] Retrieving Checkout Session.', [
            'session_id' => $orderId,
        ]);

        $response = $this->request('GET', "/v1/checkout/sessions/{$orderId}", [], $account);

        return [
            'id'              => $response['id'] ?? '',
            'status'          => $response['status'] ?? 'unknown',
            'payment_status'  => $response['payment_status'] ?? 'unknown',
            'payment_intent'  => $response['payment_intent'] ?? null,
            'amount_total'    => isset($response['amount_total']) ? $this->fromSmallestUnit((string) $response['amount_total'], $response['currency'] ?? 'usd') : '0.00',
            'currency'        => $response['currency'] ?? 'usd',
            'metadata'        => $response['metadata'] ?? [],
            'raw'             => $response,
        ];
    }

    /* ----------------------------------------------------------------
     |  私有方法
     | ---------------------------------------------------------------- */

    /**
     * 发送 Stripe API 请求
     *
     * @param  string         $method   HTTP 方法（GET / POST）
     * @param  string         $endpoint API 端点（如 /v1/checkout/sessions）
     * @param  array          $params   请求参数（form-urlencoded）
     * @param  PaymentAccount $account  支付账号
     * @return array 响应数据
     *
     * @throws \RuntimeException API 调用失败
     */
    private function request(string $method, string $endpoint, array $params, PaymentAccount $account): array
    {
        $secretKey = $account->client_secret;

        if (empty($secretKey)) {
            throw new \RuntimeException("Stripe Secret Key is empty for account #{$account->id}");
        }

        $url = self::BASE_URL . $endpoint;

        try {
            $httpClient = Http::withHeaders([
                'Stripe-Version' => self::API_VERSION,
            ])->withBasicAuth($secretKey, '');

            $response = match (strtoupper($method)) {
                'GET'  => $httpClient->get($url, $params),
                'POST' => $httpClient->asForm()->post($url, $params),
                default => throw new \RuntimeException("Unsupported HTTP method: {$method}"),
            };

            $data = $response->json() ?? [];

            if ($response->failed()) {
                $errorMessage = $data['error']['message'] ?? 'Unknown Stripe error';
                $errorType    = $data['error']['type'] ?? 'api_error';

                Log::error('[StripeGateway] API request failed.', [
                    'endpoint'   => $endpoint,
                    'status'     => $response->status(),
                    'error_type' => $errorType,
                    'error_msg'  => $errorMessage,
                    'account_id' => $account->id,
                ]);

                throw new \RuntimeException("Stripe API error [{$errorType}]: {$errorMessage}", $response->status());
            }

            return $data;
        } catch (\RuntimeException $e) {
            throw $e;
        } catch (\Exception $e) {
            Log::error('[StripeGateway] Request exception.', [
                'endpoint' => $endpoint,
                'error'    => $e->getMessage(),
            ]);

            throw new \RuntimeException("Stripe API request failed: {$e->getMessage()}", 0, $e);
        }
    }

    /**
     * 将金额转换为 Stripe 最小单位（如 USD 美分）
     *
     * @param  string $amount   金额字符串（如 "49.99"）
     * @param  string $currency 小写货币代码
     * @return int 最小单位金额（如 4999）
     */
    private function toSmallestUnit(string $amount, string $currency): int
    {
        // 零小数货币列表（日元等）
        $zeroDecimalCurrencies = ['bif', 'clp', 'djf', 'gnf', 'jpy', 'kmf', 'krw', 'mga', 'pyg', 'rwf', 'ugx', 'vnd', 'vuv', 'xaf', 'xof', 'xpf'];

        if (in_array($currency, $zeroDecimalCurrencies, true)) {
            return (int) $amount;
        }

        return (int) bcmul($amount, '100', 0);
    }

    /**
     * 将 Stripe 最小单位转换回标准金额
     *
     * @param  string $amountInCents 最小单位金额字符串
     * @param  string $currency      小写货币代码
     * @return string 标准金额（如 "49.99"）
     */
    private function fromSmallestUnit(string $amountInCents, string $currency): string
    {
        $zeroDecimalCurrencies = ['bif', 'clp', 'djf', 'gnf', 'jpy', 'kmf', 'krw', 'mga', 'pyg', 'rwf', 'ugx', 'vnd', 'vuv', 'xaf', 'xof', 'xpf'];

        if (in_array($currency, $zeroDecimalCurrencies, true)) {
            return $amountInCents;
        }

        return bcdiv($amountInCents, '100', 2);
    }
}
