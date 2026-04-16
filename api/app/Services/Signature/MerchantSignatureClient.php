<?php

namespace App\Services\Signature;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

/**
 * 商户端签名客户端工具类
 *
 * 封装签名 Headers 的构造逻辑，可直接发送已签名的 HTTP 请求。
 * 适用于商户集成测试、SDK 示例及文档演示。
 *
 * 使用示例：
 * ```php
 * $client = new MerchantSignatureClient(
 *     keyId:      'mk_xxxxxxxx',
 *     privateKey: file_get_contents('/path/to/private.pem'),
 *     baseUrl:    'https://api.jerseyholic.com'
 * );
 *
 * // 发送 GET 请求
 * $response = $client->request('GET', '/api/v1/merchant/orders');
 *
 * // 发送 POST 请求
 * $response = $client->request('POST', '/api/v1/merchant/orders', [
 *     'product_id' => 123,
 *     'quantity'    => 2,
 * ]);
 * ```
 */
class MerchantSignatureClient
{
    /**
     * @param string $keyId      商户 API 密钥 ID（如 mk_xxxxxxxx）
     * @param string $privateKey RSA 私钥（PEM 格式完整内容）
     * @param string $baseUrl    API 基础 URL（不含尾斜杠）
     */
    public function __construct(
        private readonly string $keyId,
        private readonly string $privateKey,
        private readonly string $baseUrl,
    ) {}

    /**
     * 发送已签名的 HTTP 请求
     *
     * @param  string $method HTTP 方法（GET/POST/PUT/PATCH/DELETE）
     * @param  string $uri    请求路径（如 /api/v1/merchant/orders）
     * @param  array  $data   请求数据（POST/PUT/PATCH 时作为 JSON body）
     * @return Response Laravel HTTP Client 响应
     */
    public function request(string $method, string $uri, array $data = []): Response
    {
        $method = strtoupper($method);
        $body   = in_array($method, ['POST', 'PUT', 'PATCH']) && !empty($data)
            ? json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
            : '';

        // GET/DELETE 请求：将 data 作为 query 参数拼接到 URI
        $fullUri = $uri;
        if (in_array($method, ['GET', 'DELETE']) && !empty($data)) {
            $separator = str_contains($uri, '?') ? '&' : '?';
            $fullUri   = $uri . $separator . http_build_query($data);
        }

        $headers = $this->signHeaders($method, $fullUri, $body);

        $pendingRequest = Http::baseUrl($this->baseUrl)
            ->withHeaders($headers)
            ->acceptJson();

        return match ($method) {
            'GET'    => $pendingRequest->get($fullUri),
            'POST'   => $pendingRequest->withBody($body, 'application/json')->post($fullUri),
            'PUT'    => $pendingRequest->withBody($body, 'application/json')->put($fullUri),
            'PATCH'  => $pendingRequest->withBody($body, 'application/json')->patch($fullUri),
            'DELETE' => $pendingRequest->delete($fullUri),
            default  => throw new \InvalidArgumentException("Unsupported HTTP method: {$method}"),
        };
    }

    /**
     * 为请求生成签名 Headers
     *
     * 返回的 Headers 可直接合并到任何 HTTP 客户端的请求头中。
     *
     * @param  string $method HTTP 方法（大写）
     * @param  string $uri    请求 URI（含查询参数）
     * @param  string $body   请求体原始内容（无 body 时传空字符串）
     * @return array<string, string> 签名相关请求头
     */
    public function signHeaders(string $method, string $uri, string $body = ''): array
    {
        $timestamp = (string) time();
        $nonce     = SignatureHelper::generateNonce();

        $signString = SignatureHelper::buildSignString($method, $uri, $timestamp, $body);
        $signature  = SignatureHelper::sign($signString, $this->privateKey);

        return [
            'X-Merchant-Key-Id' => $this->keyId,
            'X-Signature'       => $signature,
            'X-Timestamp'       => $timestamp,
            'X-Nonce'           => $nonce,
        ];
    }
}
