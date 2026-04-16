<?php

declare(strict_types=1);

namespace Tests\Unit\Performance;

use App\Models\Central\PaymentAccount;
use App\Services\Payment\AccountHealthScoreService;
use App\Services\Payment\AccountLifecycleService;
use App\Services\Payment\CommissionService;
use App\Services\Signature\SignatureHelper;
use Tests\TestCase;

class PaymentPerformanceTest extends TestCase
{
    /* ----------------------------------------------------------------
     |  佣金计算执行时间 < 10ms
     | ---------------------------------------------------------------- */

    public function test_commission_volume_discount_calculation_under_10ms(): void
    {
        $service = new CommissionService();

        $start = microtime(true);

        for ($i = 0; $i < 100; $i++) {
            $service->calculateVolumeDiscount((string) rand(0, 200000));
        }

        $elapsed = (microtime(true) - $start) * 1000;
        $perCall = $elapsed / 100;

        $this->assertLessThan(10, $perCall, "Volume discount calculation took {$perCall}ms per call");
    }

    /* ----------------------------------------------------------------
     |  签名验证执行时间 < 10ms
     | ---------------------------------------------------------------- */

    public function test_signature_verification_under_10ms(): void
    {
        // 生成 RSA 密钥对
        $config = ['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA];
        $res = openssl_pkey_new($config);
        openssl_pkey_export($res, $privateKey);
        $details = openssl_pkey_get_details($res);
        $publicKey = $details['key'];

        $signString = SignatureHelper::buildSignString('POST', '/api/v1/orders', (string) time(), '{"test":true}');
        $signature = SignatureHelper::sign($signString, $privateKey);

        $start = microtime(true);

        for ($i = 0; $i < 50; $i++) {
            SignatureHelper::verify($signString, $signature, $publicKey);
        }

        $elapsed = (microtime(true) - $start) * 1000;
        $perCall = $elapsed / 50;

        $this->assertLessThan(10, $perCall, "Signature verification took {$perCall}ms per call");
    }

    /* ----------------------------------------------------------------
     |  健康度评分计算 < 50ms
     | ---------------------------------------------------------------- */

    public function test_health_score_calculation_under_50ms(): void
    {
        $lifecycleService = $this->createMock(AccountLifecycleService::class);
        $service = new AccountHealthScoreService($lifecycleService);

        $account = new PaymentAccount([
            'total_success_count' => 500,
            'total_fail_count'    => 10,
            'total_refund_count'  => 5,
            'total_dispute_count' => 2,
            'health_score'        => 80,
            'lifecycle_stage'     => PaymentAccount::LIFECYCLE_MATURE,
            'last_used_at'        => now()->subDays(2),
            'created_at'          => now()->subDays(100),
        ]);

        $start = microtime(true);

        for ($i = 0; $i < 100; $i++) {
            $service->calculate($account);
        }

        $elapsed = (microtime(true) - $start) * 1000;
        $perCall = $elapsed / 100;

        $this->assertLessThan(50, $perCall, "Health score calculation took {$perCall}ms per call");
    }

    /* ----------------------------------------------------------------
     |  签名字符串构建 < 5ms
     | ---------------------------------------------------------------- */

    public function test_sign_string_build_under_5ms(): void
    {
        $body = str_repeat('{"key":"value"},', 100); // ~1.6KB body

        $start = microtime(true);

        for ($i = 0; $i < 1000; $i++) {
            SignatureHelper::buildSignString('POST', '/api/v1/test', (string) time(), $body);
        }

        $elapsed = (microtime(true) - $start) * 1000;
        $perCall = $elapsed / 1000;

        $this->assertLessThan(5, $perCall, "Sign string build took {$perCall}ms per call");
    }

    /* ----------------------------------------------------------------
     |  bcmath 计算性能
     | ---------------------------------------------------------------- */

    public function test_bcmath_operations_performance(): void
    {
        $start = microtime(true);

        for ($i = 0; $i < 10000; $i++) {
            $base = '15.00';
            $vol = '3.00';
            $loy = '1.00';
            $rate = bcsub($base, $vol, 2);
            $rate = bcsub($rate, $loy, 2);
            $rateDecimal = bcdiv($rate, '100', 4);
            bcmul('1000.00', $rateDecimal, 2);
        }

        $elapsed = (microtime(true) - $start) * 1000;

        $this->assertLessThan(500, $elapsed, "10000 bcmath commission calculations took {$elapsed}ms");
    }
}
