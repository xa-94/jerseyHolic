<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/**
 * PayPal Webhook 签名验证中间件（M3-008）
 *
 * 通过 PayPal Webhook Signature Verification API 验证请求合法性。
 * 验证 Headers：
 *  - PAYPAL-TRANSMISSION-ID
 *  - PAYPAL-TRANSMISSION-TIME
 *  - PAYPAL-TRANSMISSION-SIG
 *  - PAYPAL-CERT-URL
 *  - PAYPAL-AUTH-ALGO
 *
 * 验证失败返回 HTTP 403。
 */
class VerifyPayPalWebhook
{
    /** 必需的 PayPal Webhook 签名 Headers */
    private const REQUIRED_HEADERS = [
        'PAYPAL-TRANSMISSION-ID',
        'PAYPAL-TRANSMISSION-TIME',
        'PAYPAL-TRANSMISSION-SIG',
        'PAYPAL-CERT-URL',
        'PAYPAL-AUTH-ALGO',
    ];

    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        // 检查必需 Headers 是否齐全
        foreach (self::REQUIRED_HEADERS as $header) {
            if (!$request->hasHeader($header)) {
                Log::warning('[VerifyPayPalWebhook] Missing required header', [
                    'missing_header' => $header,
                    'ip'             => $request->ip(),
                ]);
                return response()->json([
                    'code'    => 40300,
                    'message' => 'Missing PayPal webhook signature header',
                    'data'    => null,
                ], 403);
            }
        }

        $webhookId = config('services.paypal.webhook_id');
        if (empty($webhookId)) {
            Log::error('[VerifyPayPalWebhook] PayPal webhook_id not configured');
            return response()->json([
                'code'    => 50000,
                'message' => 'Webhook verification not configured',
                'data'    => null,
            ], 500);
        }

        // 构建验证请求体
        $verifyPayload = [
            'auth_algo'         => $request->header('PAYPAL-AUTH-ALGO'),
            'cert_url'          => $request->header('PAYPAL-CERT-URL'),
            'transmission_id'   => $request->header('PAYPAL-TRANSMISSION-ID'),
            'transmission_sig'  => $request->header('PAYPAL-TRANSMISSION-SIG'),
            'transmission_time' => $request->header('PAYPAL-TRANSMISSION-TIME'),
            'webhook_id'        => $webhookId,
            'webhook_event'     => json_decode($request->getContent(), true),
        ];

        try {
            $verified = $this->verifyWithPayPal($verifyPayload);
        } catch (\Throwable $e) {
            Log::error('[VerifyPayPalWebhook] Verification API call failed', [
                'error' => $e->getMessage(),
                'ip'    => $request->ip(),
            ]);
            return response()->json([
                'code'    => 40300,
                'message' => 'Webhook signature verification failed',
                'data'    => null,
            ], 403);
        }

        if (!$verified) {
            Log::warning('[VerifyPayPalWebhook] Signature verification failed', [
                'transmission_id' => $request->header('PAYPAL-TRANSMISSION-ID'),
                'ip'              => $request->ip(),
            ]);
            return response()->json([
                'code'    => 40300,
                'message' => 'Invalid webhook signature',
                'data'    => null,
            ], 403);
        }

        return $next($request);
    }

    /**
     * 调用 PayPal Verify Webhook Signature API
     *
     * POST /v1/notifications/verify-webhook-signature
     *
     * @param  array $payload 验证请求体
     * @return bool  verification_status === 'SUCCESS'
     * @throws GuzzleException
     */
    private function verifyWithPayPal(array $payload): bool
    {
        $baseUri = config('services.paypal.base_uri', 'https://api-m.sandbox.paypal.com');

        $client = new Client([
            'base_uri'        => $baseUri,
            'timeout'         => 15,
            'connect_timeout' => 5,
        ]);

        // 使用 PayPal 系统级 OAuth2 token（不依赖具体账号）
        // 此处取第一个可用账号的凭证进行认证
        $tokenResponse = $this->getSystemToken($client);

        $response = $client->post('/v1/notifications/verify-webhook-signature', [
            'headers' => [
                'Authorization' => "Bearer {$tokenResponse}",
                'Content-Type'  => 'application/json',
            ],
            'json' => $payload,
        ]);

        $data = json_decode($response->getBody()->getContents(), true);

        $status = $data['verification_status'] ?? '';

        Log::info('[VerifyPayPalWebhook] Verification result', [
            'status'          => $status,
            'transmission_id' => $payload['transmission_id'] ?? '',
        ]);

        return $status === 'SUCCESS';
    }

    /**
     * 获取系统级 PayPal OAuth2 Token（用于 Webhook 验签）
     *
     * 从环境变量读取系统级 client_id/secret，或从数据库取第一个活跃 PayPal 账号。
     *
     * @param  Client $client
     * @return string access_token
     * @throws GuzzleException
     */
    private function getSystemToken(Client $client): string
    {
        // 优先使用环境变量中的系统级凭证
        $clientId     = config('services.paypal.client_id', '');
        $clientSecret = config('services.paypal.client_secret', '');

        // 如果没有配置系统级凭证，从 webhook_id 关联的账号获取
        if (empty($clientId) || empty($clientSecret)) {
            $account = \App\Models\Central\PaymentAccount::query()
                ->where('pay_method', 'paypal')
                ->where('status', 1)
                ->whereNotNull('webhook_id')
                ->where('webhook_id', config('services.paypal.webhook_id'))
                ->first();

            if ($account === null) {
                throw new \RuntimeException('No PayPal account found for webhook verification');
            }

            $clientId     = $account->client_id;
            $clientSecret = $account->client_secret;
        }

        $cacheKey = 'paypal_webhook_verify_token';
        $cached   = \Illuminate\Support\Facades\Cache::get($cacheKey);
        if ($cached !== null) {
            return $cached;
        }

        $response = $client->post('/v1/oauth2/token', [
            'auth'        => [$clientId, $clientSecret],
            'form_params' => ['grant_type' => 'client_credentials'],
            'headers'     => ['Accept' => 'application/json'],
        ]);

        $data      = json_decode($response->getBody()->getContents(), true);
        $token     = $data['access_token'] ?? '';
        $expiresIn = (int) ($data['expires_in'] ?? 3600);

        if ($token !== '') {
            \Illuminate\Support\Facades\Cache::put($cacheKey, $token, max($expiresIn - 60, 60));
        }

        return $token;
    }
}
