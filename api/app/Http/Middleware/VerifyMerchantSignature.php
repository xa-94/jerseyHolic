<?php

namespace App\Http\Middleware;

use App\Models\Central\MerchantApiKey;
use App\Services\Signature\SignatureHelper;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;
use Symfony\Component\HttpFoundation\Response;

/**
 * 商户 API 签名验证中间件
 *
 * 验证流程：
 *  1. 检查必要请求头（X-Merchant-Key-Id / X-Signature / X-Timestamp / X-Nonce）
 *  2. Timestamp 防重放校验（±300 秒）
 *  3. Nonce 唯一性校验（Redis SET NX，TTL 300s）
 *  4. 查找公钥（Redis 缓存 1h）并校验密钥状态
 *  5. 构造待签字符串并执行 RSA-SHA256 验签
 *  6. 验证通过 → 将 merchant_key_id 和 merchant_id 注入 request attributes
 *
 * 签名字符串格式：
 *   HTTP_METHOD + "\n" + REQUEST_URI + "\n" + X-Timestamp + "\n" + SHA256(REQUEST_BODY)
 */
class VerifyMerchantSignature
{
    /**
     * Timestamp 最大允许偏差（秒）
     */
    private const TIMESTAMP_TOLERANCE = 300;

    /**
     * Nonce 缓存 TTL（秒）— 与 Timestamp 容差一致
     */
    private const NONCE_TTL = 300;

    /**
     * 公钥缓存 TTL（秒）— 1 小时
     */
    private const PUBKEY_CACHE_TTL = 3600;

    /**
     * 必要的签名请求头
     */
    private const REQUIRED_HEADERS = [
        'X-Merchant-Key-Id',
        'X-Signature',
        'X-Timestamp',
        'X-Nonce',
    ];

    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        // 1. 检查必要请求头
        foreach (self::REQUIRED_HEADERS as $header) {
            if (!$request->hasHeader($header)) {
                return $this->reject('Missing required signature headers');
            }
        }

        $keyId     = $request->header('X-Merchant-Key-Id');
        $signature = $request->header('X-Signature');
        $timestamp = $request->header('X-Timestamp');
        $nonce     = $request->header('X-Nonce');

        // 2. Timestamp 防重放校验
        if (abs(time() - intval($timestamp)) > self::TIMESTAMP_TOLERANCE) {
            return $this->reject('Request timestamp expired');
        }

        // 3. Nonce 唯一性校验（Redis 原子操作）
        $nonceKey = 'api_nonce:' . $nonce;
        $acquired = Redis::set($nonceKey, 1, 'EX', self::NONCE_TTL, 'NX');

        if (!$acquired) {
            return $this->reject('Duplicate request nonce');
        }

        // 4. 查找公钥（带 Redis 缓存）
        $apiKey = $this->resolveApiKey($keyId);

        if ($apiKey === null) {
            return $this->reject('API key not found');
        }

        if ($apiKey->isRevoked()) {
            return $this->reject('API key has been revoked');
        }

        if ($apiKey->isExpired()) {
            return $this->reject('API key has expired');
        }

        if (!$apiKey->isActive() && !$apiKey->isRotating()) {
            return $this->reject('API key is not active');
        }

        // 5. 构造待签字符串并验签
        $method = strtoupper($request->method());
        $uri    = $request->getRequestUri(); // 含查询参数
        $body   = $request->getContent() ?: '';

        $signString = SignatureHelper::buildSignString($method, $uri, $timestamp, $body);

        try {
            $valid = SignatureHelper::verify($signString, $signature, $apiKey->public_key);
        } catch (\RuntimeException $e) {
            Log::warning('Signature verification error', [
                'key_id' => $keyId,
                'error'  => $e->getMessage(),
            ]);
            return $this->reject('Invalid signature');
        }

        if (!$valid) {
            return $this->reject('Invalid signature');
        }

        // 6. 验证通过 → 注入请求属性
        $request->attributes->set('merchant_key_id', $keyId);
        $request->attributes->set('merchant_id', $apiKey->merchant_id);

        return $next($request);
    }

    /**
     * 从缓存或数据库获取 API 密钥
     *
     * 缓存策略：公钥 + 密钥元数据缓存到 Redis，TTL 1 小时。
     * 吊销/过期时通过 MerchantKeyService 清除缓存。
     *
     * @param  string $keyId 密钥 ID（如 mk_xxxxxxxx）
     * @return MerchantApiKey|null
     */
    private function resolveApiKey(string $keyId): ?MerchantApiKey
    {
        $cacheKey = 'merchant_pubkey:' . $keyId;

        return Cache::remember($cacheKey, self::PUBKEY_CACHE_TTL, function () use ($keyId) {
            return MerchantApiKey::where('key_id', $keyId)->first();
        });
    }

    /**
     * 返回统一的 401 JSON 错误响应
     *
     * @param  string $message 错误消息
     * @return JsonResponse
     */
    private function reject(string $message): JsonResponse
    {
        return response()->json([
            'code'    => 401,
            'message' => $message,
            'data'    => null,
        ], 401);
    }
}
