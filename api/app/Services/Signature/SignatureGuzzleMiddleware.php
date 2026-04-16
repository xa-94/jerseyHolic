<?php

namespace App\Services\Signature;

use Closure;
use GuzzleHttp\Promise\PromiseInterface;
use Psr\Http\Message\RequestInterface;

/**
 * Guzzle HTTP 客户端签名中间件
 *
 * 自动为出站请求添加 RSA-SHA256 签名 Headers。
 * 适用于商户集成 SDK 或与第三方系统的签名通信。
 *
 * 使用示例：
 * ```php
 * use GuzzleHttp\Client;
 * use GuzzleHttp\HandlerStack;
 *
 * $stack = HandlerStack::create();
 * $stack->push(new SignatureGuzzleMiddleware('mk_xxx', $privateKeyPem));
 *
 * $client = new Client([
 *     'handler'  => $stack,
 *     'base_uri' => 'https://api.jerseyholic.com',
 * ]);
 *
 * $response = $client->post('/api/v1/merchant/orders', [
 *     'json' => ['product_id' => 123],
 * ]);
 * ```
 */
class SignatureGuzzleMiddleware
{
    /**
     * @param string $keyId      商户 API 密钥 ID
     * @param string $privateKey RSA 私钥（PEM 格式）
     */
    public function __construct(
        private readonly string $keyId,
        private readonly string $privateKey,
    ) {}

    /**
     * Guzzle 中间件入口
     *
     * @param  callable $handler 下一个处理器
     * @return Closure
     */
    public function __invoke(callable $handler): Closure
    {
        return function (RequestInterface $request, array $options) use ($handler): PromiseInterface {
            $method    = strtoupper($request->getMethod());
            $uri       = (string) $request->getUri()->getPath();
            $query     = $request->getUri()->getQuery();
            $timestamp = (string) time();
            $nonce     = SignatureHelper::generateNonce();

            // URI 含查询参数时拼接
            if ($query !== '') {
                $uri .= '?' . $query;
            }

            // 读取请求体
            $body = (string) $request->getBody();

            // 构造签名
            $signString = SignatureHelper::buildSignString($method, $uri, $timestamp, $body);
            $signature  = SignatureHelper::sign($signString, $this->privateKey);

            // 添加签名 Headers
            $request = $request
                ->withHeader('X-Merchant-Key-Id', $this->keyId)
                ->withHeader('X-Signature', $signature)
                ->withHeader('X-Timestamp', $timestamp)
                ->withHeader('X-Nonce', $nonce);

            // 重置 body stream 位置（读取后需要 rewind）
            $request->getBody()->rewind();

            return $handler($request, $options);
        };
    }
}
