<?php

declare(strict_types=1);

namespace Tests\Feature\Security;

use App\Http\Middleware\VerifyMerchantSignature;
use App\Models\Central\MerchantApiKey;
use App\Services\Signature\SignatureHelper;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Redis;
use Mockery;
use Tests\TestCase;

class SignatureVerificationTest extends TestCase
{
    private string $privateKey = '';
    private string $publicKey = '';

    protected function setUp(): void
    {
        parent::setUp();

        // 生成测试用 RSA 密钥对
        $config = [
            'private_key_bits' => 2048,
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
        ];
        $res = openssl_pkey_new($config);
        openssl_pkey_export($res, $this->privateKey);
        $details = openssl_pkey_get_details($res);
        $this->publicKey = $details['key'];
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /* ----------------------------------------------------------------
     |  RSA-SHA256 签名验证成功
     | ---------------------------------------------------------------- */

    public function test_rsa_sha256_signature_verification_success(): void
    {
        $method    = 'POST';
        $uri       = '/api/v1/orders';
        $timestamp = (string) time();
        $body      = '{"order_no":"ORD-001"}';

        $signString = SignatureHelper::buildSignString($method, $uri, $timestamp, $body);
        $signature  = SignatureHelper::sign($signString, $this->privateKey);

        $valid = SignatureHelper::verify($signString, $signature, $this->publicKey);

        $this->assertTrue($valid);
    }

    /* ----------------------------------------------------------------
     |  签名不匹配返回 false
     | ---------------------------------------------------------------- */

    public function test_signature_mismatch_returns_false(): void
    {
        $signString = SignatureHelper::buildSignString('GET', '/api/test', (string) time(), '');
        $signature  = SignatureHelper::sign($signString, $this->privateKey);

        // 篡改待签字符串
        $tamperedSignString = SignatureHelper::buildSignString('GET', '/api/tampered', (string) time(), '');

        $valid = SignatureHelper::verify($tamperedSignString, $signature, $this->publicKey);

        $this->assertFalse($valid);
    }

    /* ----------------------------------------------------------------
     |  Timestamp 过期（>5min）返回 401
     | ---------------------------------------------------------------- */

    public function test_expired_timestamp_is_rejected(): void
    {
        $middleware = new VerifyMerchantSignature();

        $expiredTimestamp = (string) (time() - 400); // > 300s tolerance

        $request = Request::create('/api/test', 'GET', [], [], [], [
            'HTTP_X_MERCHANT_KEY_ID' => 'mk_test_001',
            'HTTP_X_SIGNATURE'       => 'dummy_signature',
            'HTTP_X_TIMESTAMP'       => $expiredTimestamp,
            'HTTP_X_NONCE'           => 'unique-nonce-001',
        ]);

        $response = $middleware->handle($request, function () {
            return response()->json(['ok' => true]);
        });

        $this->assertSame(401, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertStringContainsString('expired', strtolower($data['message']));
    }

    /* ----------------------------------------------------------------
     |  Nonce 重放拦截
     | ---------------------------------------------------------------- */

    public function test_nonce_replay_is_rejected(): void
    {
        $middleware = new VerifyMerchantSignature();

        $timestamp = (string) time();

        // 第一次请求设置 Nonce
        Redis::shouldReceive('set')
            ->once()
            ->with('api_nonce:nonce-replay-test', 1, 'EX', 300, 'NX')
            ->andReturn(null); // 已存在 → 拒绝

        $request = Request::create('/api/test', 'GET', [], [], [], [
            'HTTP_X_MERCHANT_KEY_ID' => 'mk_test_001',
            'HTTP_X_SIGNATURE'       => 'dummy',
            'HTTP_X_TIMESTAMP'       => $timestamp,
            'HTTP_X_NONCE'           => 'nonce-replay-test',
        ]);

        $response = $middleware->handle($request, function () {
            return response()->json(['ok' => true]);
        });

        $this->assertSame(401, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertStringContainsString('nonce', strtolower($data['message']));
    }

    /* ----------------------------------------------------------------
     |  缺失 Header 返回 401
     | ---------------------------------------------------------------- */

    public function test_missing_headers_returns_401(): void
    {
        $middleware = new VerifyMerchantSignature();

        // 缺少签名 headers
        $request = Request::create('/api/test', 'GET');

        $response = $middleware->handle($request, function () {
            return response()->json(['ok' => true]);
        });

        $this->assertSame(401, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertStringContainsString('missing', strtolower($data['message']));
    }

    /* ----------------------------------------------------------------
     |  签名字符串拼接顺序验证
     | ---------------------------------------------------------------- */

    public function test_sign_string_concatenation_order(): void
    {
        $method    = 'POST';
        $uri       = '/api/v1/payments';
        $timestamp = '1700000000';
        $body      = '{"amount":"100.00"}';
        $bodyHash  = hash('sha256', $body);

        $expected = "POST\n/api/v1/payments\n1700000000\n{$bodyHash}";
        $actual   = SignatureHelper::buildSignString($method, $uri, $timestamp, $body);

        $this->assertSame($expected, $actual);
    }
}
