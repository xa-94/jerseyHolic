<?php

namespace App\Services\Signature;

use Illuminate\Support\Str;
use RuntimeException;

/**
 * RSA-SHA256 签名/验签辅助工具类
 *
 * 提供待签字符串构造、私钥签名、公钥验签和 Nonce 生成能力。
 * 签名算法固定为 RSA-SHA256，待签字符串格式：
 *   HTTP_METHOD + "\n" + REQUEST_URI + "\n" + TIMESTAMP + "\n" + SHA256(BODY)
 */
class SignatureHelper
{
    /**
     * 构造待签字符串
     *
     * @param  string $method    HTTP 方法（大写：GET/POST/PUT/PATCH/DELETE）
     * @param  string $uri       请求 URI（含查询参数）
     * @param  string $timestamp Unix 时间戳
     * @param  string $body      请求体原始内容（GET 等无 body 时传空字符串）
     * @return string 待签字符串
     */
    public static function buildSignString(
        string $method,
        string $uri,
        string $timestamp,
        string $body = ''
    ): string {
        $bodyHash = hash('sha256', $body);

        return strtoupper($method) . "\n"
            . $uri . "\n"
            . $timestamp . "\n"
            . $bodyHash;
    }

    /**
     * 使用 RSA 私钥签名
     *
     * @param  string $signString 待签字符串
     * @param  string $privateKey RSA 私钥（PEM 格式）
     * @return string Base64 编码的签名
     *
     * @throws RuntimeException 签名失败时抛出
     */
    public static function sign(string $signString, string $privateKey): string
    {
        $privateKeyResource = openssl_pkey_get_private($privateKey);
        if ($privateKeyResource === false) {
            throw new RuntimeException('Invalid private key: ' . openssl_error_string());
        }

        $signature = '';
        $result = openssl_sign($signString, $signature, $privateKeyResource, OPENSSL_ALGO_SHA256);

        if ($result === false) {
            throw new RuntimeException('Signing failed: ' . openssl_error_string());
        }

        return base64_encode($signature);
    }

    /**
     * 使用 RSA 公钥验签
     *
     * @param  string $signString 待签字符串
     * @param  string $signature  Base64 编码的签名
     * @param  string $publicKey  RSA 公钥（PEM 格式）
     * @return bool 验签通过返回 true，失败返回 false
     *
     * @throws RuntimeException 公钥无效或验签过程异常时抛出
     */
    public static function verify(string $signString, string $signature, string $publicKey): bool
    {
        $publicKeyResource = openssl_pkey_get_public($publicKey);
        if ($publicKeyResource === false) {
            throw new RuntimeException('Invalid public key: ' . openssl_error_string());
        }

        $decodedSignature = base64_decode($signature, true);
        if ($decodedSignature === false) {
            return false;
        }

        $result = openssl_verify($signString, $decodedSignature, $publicKeyResource, OPENSSL_ALGO_SHA256);

        if ($result === -1) {
            throw new RuntimeException('Signature verification error: ' . openssl_error_string());
        }

        return $result === 1;
    }

    /**
     * 生成唯一的 Nonce（UUID v4 格式）
     *
     * @return string UUID v4 字符串
     */
    public static function generateNonce(): string
    {
        return (string) Str::uuid();
    }
}
