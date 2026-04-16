<?php

declare(strict_types=1);

namespace App\Services\Payment;

use App\DTOs\SafeDescriptionDTO;
use App\Enums\ErrorCode;
use App\Exceptions\BusinessException;
use App\Models\Central\PaymentAccount;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * PayPal 支付网关实现（M3-008）
 *
 * 实现 PaymentGatewayInterface 的所有方法，以及 PayPal 特有的 uploadTracking()。
 *
 * 关键安全：
 *  - 商品名称/描述通过 SafeDescriptionService 脱敏
 *  - 不传 image_url、不传商品 URL
 *  - 价格永不替换，保持真实金额
 *
 * 认证：OAuth2 Client Credentials，access_token 缓存于 Redis。
 */
class PayPalGateway implements PaymentGatewayInterface
{
    /** Token 缓存 Key 前缀 */
    private const TOKEN_CACHE_PREFIX = 'paypal_token';

    /** Token 过期提前量（秒），防止临界过期 */
    private const TOKEN_EXPIRY_BUFFER = 60;

    public function __construct(
        private readonly SafeDescriptionService $safeDescriptionService,
    ) {}

    /* ================================================================
     |  PaymentGatewayInterface 实现
     | ================================================================ */

    /**
     * 创建 PayPal 订单
     *
     * 调用 POST /v2/checkout/orders，intent=CAPTURE。
     * purchase_units 中的商品名称/描述使用安全文本，价格保持真实金额。
     *
     * @param  array          $orderData 需包含：
     *   - store_id        int     站点 ID
     *   - product_category string  商品分类（用于脱敏查询）
     *   - order_no        string  系统订单号
     *   - amount          string  总金额（bcmath 精度）
     *   - currency        string  货币代码（默认 USD）
     *   - return_url      string  支付成功回调 URL
     *   - cancel_url      string  用户取消回调 URL
     *   - items           array   商品明细 [['name','quantity','unit_amount','category']]
     * @param  PaymentAccount $account 选号结果
     * @return array ['order_id', 'status', 'approve_url', 'raw']
     */
    public function createOrder(array $orderData, PaymentAccount $account): array
    {
        $storeId  = (int) ($orderData['store_id'] ?? 0);
        $category = $orderData['product_category'] ?? 'default';

        // 获取安全商品描述（三层防护）
        $safeDesc = $this->safeDescriptionService->resolve($storeId ?: null, $category);

        $currency = strtoupper($orderData['currency'] ?? 'USD');
        $amount   = $orderData['amount']; // 价格永不替换

        // 构建安全 line items（名称/描述脱敏，价格保持真实）
        $safeItems = $this->buildSafeItems($orderData['items'] ?? [], $safeDesc, $currency);

        $payload = [
            'intent'         => 'CAPTURE',
            'purchase_units' => [
                [
                    'reference_id' => $orderData['order_no'] ?? uniqid('jh_'),
                    'custom_id'    => (string) ($orderData['store_id'] ?? 0),
                    'description'  => $safeDesc->safeDescription,
                    'amount'       => [
                        'currency_code' => $currency,
                        'value'         => $amount,
                        'breakdown'     => [
                            'item_total' => [
                                'currency_code' => $currency,
                                'value'         => $amount,
                            ],
                        ],
                    ],
                    'items' => $safeItems,
                ],
            ],
            'application_context' => [
                'return_url' => $orderData['return_url'] ?? $account->success_url,
                'cancel_url' => $orderData['cancel_url'] ?? $account->cancel_url,
                'brand_name' => $orderData['brand_name'] ?? 'JerseyHolic',
                'user_action' => 'PAY_NOW',
            ],
        ];

        Log::info('[PayPalGateway] Creating order', [
            'order_no'     => $orderData['order_no'] ?? null,
            'amount'       => $amount,
            'currency'     => $currency,
            'account_id'   => $account->id,
            'safe_desc'    => $safeDesc->safeName,
        ]);

        $response = $this->request('POST', '/v2/checkout/orders', $account, $payload);

        // 提取 approve link
        $approveUrl = '';
        foreach ($response['links'] ?? [] as $link) {
            if (($link['rel'] ?? '') === 'approve') {
                $approveUrl = $link['href'];
                break;
            }
        }

        return [
            'order_id'    => $response['id'] ?? '',
            'status'      => $response['status'] ?? '',
            'approve_url' => $approveUrl,
            'raw'         => $response,
        ];
    }

    /**
     * 捕获（扣款）已批准的 PayPal 订单
     *
     * @param  string         $orderId PayPal 订单 ID
     * @param  PaymentAccount $account 对应支付账号
     * @return array ['capture_id', 'status', 'amount', 'currency', 'raw']
     */
    public function captureOrder(string $orderId, PaymentAccount $account): array
    {
        Log::info('[PayPalGateway] Capturing order', [
            'paypal_order_id' => $orderId,
            'account_id'      => $account->id,
        ]);

        $response = $this->request('POST', "/v2/checkout/orders/{$orderId}/capture", $account);

        // 从 purchase_units 中提取第一个 capture
        $capture   = $response['purchase_units'][0]['payments']['captures'][0] ?? [];
        $captureId = $capture['id'] ?? '';
        $status    = $capture['status'] ?? ($response['status'] ?? '');
        $amount    = $capture['amount']['value'] ?? '';
        $currency  = $capture['amount']['currency_code'] ?? '';

        return [
            'capture_id' => $captureId,
            'status'     => $status,
            'amount'     => $amount,
            'currency'   => $currency,
            'raw'        => $response,
        ];
    }

    /**
     * 退款（全额或部分）
     *
     * @param  string         $captureId 捕获 ID
     * @param  string         $amount    退款金额（bcmath 精度字符串）
     * @param  string         $currency  货币代码
     * @param  PaymentAccount $account   对应支付账号
     * @return array ['refund_id', 'status', 'amount', 'raw']
     */
    public function refundCapture(string $captureId, string $amount, string $currency, PaymentAccount $account): array
    {
        Log::info('[PayPalGateway] Refunding capture', [
            'capture_id' => $captureId,
            'amount'     => $amount,
            'currency'   => $currency,
            'account_id' => $account->id,
        ]);

        $payload = [];
        // 部分退款需传金额；全额退款可不传（PayPal 自动全额）
        if ($amount !== '' && $amount !== '0') {
            $payload['amount'] = [
                'value'         => $amount,
                'currency_code' => strtoupper($currency),
            ];
        }

        $response = $this->request('POST', "/v2/payments/captures/{$captureId}/refund", $account, $payload ?: null);

        return [
            'refund_id' => $response['id'] ?? '',
            'status'    => $response['status'] ?? '',
            'amount'    => $response['amount']['value'] ?? $amount,
            'raw'       => $response,
        ];
    }

    /**
     * 查询 PayPal 订单状态
     *
     * @param  string         $orderId PayPal 订单 ID
     * @param  PaymentAccount $account 对应支付账号
     * @return array ['order_id', 'status', 'raw']
     */
    public function getOrderStatus(string $orderId, PaymentAccount $account): array
    {
        $response = $this->request('GET', "/v2/checkout/orders/{$orderId}", $account);

        return [
            'order_id' => $response['id'] ?? $orderId,
            'status'   => $response['status'] ?? '',
            'raw'      => $response,
        ];
    }

    /* ================================================================
     |  PayPal 特有方法
     | ================================================================ */

    /**
     * 上传物流跟踪信息（卖家保护）
     *
     * 调用 POST /v1/shipping/trackers-batch 批量上传。
     *
     * @param  array          $trackers  跟踪信息 [['transaction_id', 'tracking_number', 'carrier']]
     * @param  PaymentAccount $account   对应支付账号
     * @return array          API 响应
     */
    public function uploadTracking(array $trackers, PaymentAccount $account): array
    {
        $items = array_map(fn (array $t) => [
            'transaction_id'  => $t['transaction_id'],
            'tracking_number' => $t['tracking_number'],
            'status'          => $t['status'] ?? 'SHIPPED',
            'carrier'         => $t['carrier'] ?? 'OTHER',
        ], $trackers);

        Log::info('[PayPalGateway] Uploading tracking info', [
            'account_id'     => $account->id,
            'trackers_count' => count($items),
        ]);

        return $this->request('POST', '/v1/shipping/trackers-batch', $account, [
            'trackers' => $items,
        ]);
    }

    /* ================================================================
     |  OAuth2 认证
     | ================================================================ */

    /**
     * 获取 PayPal OAuth2 Access Token（带 Redis 缓存）
     *
     * @param  PaymentAccount $account
     * @return string
     * @throws BusinessException
     */
    private function getAccessToken(PaymentAccount $account): string
    {
        $cacheKey = self::TOKEN_CACHE_PREFIX . ":{$account->id}";

        $cached = Cache::get($cacheKey);
        if ($cached !== null) {
            return $cached;
        }

        try {
            $client   = $this->buildHttpClient();
            $response = $client->post('/v1/oauth2/token', [
                'auth'        => [$account->client_id, $account->client_secret],
                'form_params' => ['grant_type' => 'client_credentials'],
                'headers'     => [
                    'Accept' => 'application/json',
                ],
            ]);

            $data = json_decode($response->getBody()->getContents(), true);
        } catch (GuzzleException $e) {
            Log::error('[PayPalGateway] OAuth2 token request failed', [
                'account_id' => $account->id,
                'error'      => $e->getMessage(),
            ]);
            throw new BusinessException(ErrorCode::PAYMENT_ERROR, 'PayPal OAuth2 认证失败: ' . $e->getMessage());
        }

        $token     = $data['access_token'] ?? '';
        $expiresIn = (int) ($data['expires_in'] ?? 3600);
        $ttl       = max($expiresIn - self::TOKEN_EXPIRY_BUFFER, 60);

        if ($token === '') {
            throw new BusinessException(ErrorCode::PAYMENT_ERROR, 'PayPal OAuth2 返回空 token');
        }

        Cache::put($cacheKey, $token, $ttl);

        Log::info('[PayPalGateway] OAuth2 token acquired', [
            'account_id' => $account->id,
            'expires_in' => $expiresIn,
            'cached_ttl' => $ttl,
        ]);

        return $token;
    }

    /* ================================================================
     |  HTTP 请求
     | ================================================================ */

    /**
     * 发送已认证的 PayPal API 请求
     *
     * @param  string         $method   HTTP 方法
     * @param  string         $uri      API 路径
     * @param  PaymentAccount $account  支付账号
     * @param  array|null     $payload  请求体（null 则不发送 body）
     * @return array          JSON 解码后的响应
     * @throws BusinessException
     */
    private function request(string $method, string $uri, PaymentAccount $account, ?array $payload = null): array
    {
        $token  = $this->getAccessToken($account);
        $client = $this->buildHttpClient();

        $options = [
            'headers' => [
                'Authorization'                 => "Bearer {$token}",
                'Content-Type'                  => 'application/json',
                'Accept'                        => 'application/json',
                'PayPal-Request-Id'             => $this->generateRequestId(),
                'Prefer'                        => 'return=representation',
            ],
        ];

        if ($payload !== null) {
            $options['json'] = $payload;
        }

        try {
            $response = $client->request($method, $uri, $options);
            $body     = $response->getBody()->getContents();

            return json_decode($body, true) ?? [];
        } catch (GuzzleException $e) {
            Log::error('[PayPalGateway] API request failed', [
                'method'     => $method,
                'uri'        => $uri,
                'account_id' => $account->id,
                'error'      => $e->getMessage(),
                'code'       => $e->getCode(),
            ]);

            throw new BusinessException(
                ErrorCode::PAYMENT_ERROR,
                "PayPal API 请求失败 [{$method} {$uri}]: " . $e->getMessage(),
            );
        }
    }

    /**
     * 构建 Guzzle HTTP Client
     *
     * base_uri 从 config('services.paypal.base_uri') 读取，支持 sandbox/live 切换。
     */
    private function buildHttpClient(): Client
    {
        return new Client([
            'base_uri' => config('services.paypal.base_uri', 'https://api-m.sandbox.paypal.com'),
            'timeout'  => 30,
            'connect_timeout' => 10,
        ]);
    }

    /* ================================================================
     |  辅助方法
     | ================================================================ */

    /**
     * 构建安全 line items（名称脱敏，价格保持真实）
     *
     * @param  array              $items    原始商品明细
     * @param  SafeDescriptionDTO $safeDesc 安全描述
     * @param  string             $currency 货币代码
     * @return array              PayPal items 数组
     */
    private function buildSafeItems(array $items, SafeDescriptionDTO $safeDesc, string $currency): array
    {
        if (empty($items)) {
            return [];
        }

        return array_map(fn (array $item) => [
            'name'        => $safeDesc->safeName,
            'description' => mb_substr($safeDesc->safeDescription, 0, 127),
            'quantity'    => (string) ($item['quantity'] ?? 1),
            'unit_amount' => [
                'currency_code' => $currency,
                'value'         => (string) ($item['unit_amount'] ?? $item['price'] ?? '0.00'),
            ],
            'category' => 'PHYSICAL_GOODS',
            // 不传 image_url、不传商品 URL
        ], $items);
    }

    /**
     * 生成幂等请求 ID（PayPal-Request-Id）
     */
    private function generateRequestId(): string
    {
        return 'jh-' . bin2hex(random_bytes(16));
    }
}
