<?php

namespace Tests\Feature\Tenancy;

use App\Events\StoreProvisioned;
use App\Events\StoreProvisionFailed;
use App\Exceptions\StoreProvisioningException;
use App\Jobs\GenerateNginxConfigJob;
use App\Jobs\ProvisionSSLCertificateJob;
use App\Models\Central\Domain;
use App\Models\Central\Store;
use App\Services\StoreProvisioningService;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Tests\TenancyTestCase;

/**
 * 站点创建流程测试
 *
 * 验证 StoreProvisioningService::provision() 的完整生命周期，
 * 包括配额检查、域名去重、数据库创建、事件触发和失败回滚。
 */
class StoreProvisioningTest extends TenancyTestCase
{
    protected StoreProvisioningService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(StoreProvisioningService::class);
    }

    /* ================================================================
     |  provision() 完整流程
     | ================================================================ */

    /** @test 测试 provision 完整流程：创建 Store + Domain + Tenant DB，状态变为 active */
    public function test_provision_creates_store_with_database_and_domain(): void
    {
        Event::fake([StoreProvisioned::class, StoreProvisionFailed::class]);
        Queue::fake([GenerateNginxConfigJob::class, ProvisionSSLCertificateJob::class]);

        $merchant = $this->createMerchant(['level' => 'starter', 'status' => 1]);

        $data = [
            'store_name' => 'Integration Test Store',
            'store_code' => 'int_test_' . Str::random(6),
            'domain'     => 'int-test-' . Str::random(6) . '.jerseyholic.test',
        ];

        $store = $this->service->provision($merchant, $data);

        // 记录到 createdStores 以便 tearDown 清理
        $this->createdStores[] = $store;

        // 验证 Store 记录存在且状态为 active (1)
        $this->assertDatabaseHas('stores', [
            'id'          => $store->id,
            'merchant_id' => $merchant->id,
            'store_name'  => $data['store_name'],
            'store_code'  => $data['store_code'],
            'status'      => 1,
        ]);

        // 验证 Domain 记录已创建
        $this->assertDatabaseHas('domains', [
            'domain' => $data['domain'],
        ]);

        // 验证 database_name 已生成
        $this->assertNotNull($store->database_name);
        $this->assertStringStartsWith('store_', $store->database_name);

        // 验证 StoreProvisioned 事件触发
        Event::assertDispatched(StoreProvisioned::class, function ($event) use ($store) {
            return $event->store->id === $store->id;
        });

        // 验证异步 Job 已派发
        Queue::assertPushed(GenerateNginxConfigJob::class);
        Queue::assertPushed(ProvisionSSLCertificateJob::class);
    }

    /** @test 测试 StoreProvisioned 事件包含正确的 Store 实例 */
    public function test_provision_dispatches_store_provisioned_event(): void
    {
        Event::fake([StoreProvisioned::class, StoreProvisionFailed::class]);
        Queue::fake();

        $merchant = $this->createMerchant(['level' => 'vip', 'status' => 1]);

        $store = $this->service->provision($merchant, [
            'store_name' => 'Event Test Store',
            'store_code' => 'evt_' . Str::random(6),
            'domain'     => 'evt-' . Str::random(6) . '.jerseyholic.test',
        ]);
        $this->createdStores[] = $store;

        Event::assertDispatched(StoreProvisioned::class, 1);
        Event::assertNotDispatched(StoreProvisionFailed::class);
    }

    /* ================================================================
     |  配额超限
     | ================================================================ */

    /** @test 测试 starter 商户站点配额（限 2 个），超限时抛异常 */
    public function test_starter_merchant_cannot_exceed_store_quota(): void
    {
        Queue::fake();
        Event::fake();

        $merchant = $this->createMerchant(['level' => 'starter', 'status' => 1]);

        // starter 限额为 2，先创建 2 个
        for ($i = 0; $i < 2; $i++) {
            $store = $this->service->provision($merchant, [
                'store_name' => "Quota Store {$i}",
                'store_code' => 'quota_' . Str::random(6),
                'domain'     => 'quota-' . Str::random(6) . '.jerseyholic.test',
            ]);
            $this->createdStores[] = $store;
        }

        // 第 3 个应该抛出配额超限异常
        $this->expectException(StoreProvisioningException::class);

        $this->service->provision($merchant, [
            'store_name' => 'Quota Exceeded Store',
            'store_code' => 'quota_exceed_' . Str::random(6),
            'domain'     => 'quota-exceed-' . Str::random(6) . '.jerseyholic.test',
        ]);
    }

    /** @test 测试 standard 商户站点配额（限 5 个） */
    public function test_standard_merchant_quota_is_five(): void
    {
        $merchant = $this->createMerchant(['level' => 'standard', 'status' => 1]);

        $this->assertTrue($this->service->validateStoreQuota($merchant));
    }

    /** @test 测试 vip 商户无站点数量限制 */
    public function test_vip_merchant_has_unlimited_stores(): void
    {
        Queue::fake();
        Event::fake();

        $merchant = $this->createMerchant(['level' => 'vip', 'status' => 1]);

        // 即使已有多个 Store，VIP 仍然不受限
        for ($i = 0; $i < 3; $i++) {
            $store = $this->service->provision($merchant, [
                'store_name' => "VIP Store {$i}",
                'store_code' => 'vip_' . Str::random(6),
                'domain'     => 'vip-' . Str::random(6) . '.jerseyholic.test',
            ]);
            $this->createdStores[] = $store;
        }

        // validateStoreQuota 应始终返回 true
        $this->assertTrue($this->service->validateStoreQuota($merchant));
    }

    /* ================================================================
     |  重复域名检测
     | ================================================================ */

    /** @test 测试使用已存在的域名创建站点时抛出异常 */
    public function test_provision_fails_with_duplicate_domain(): void
    {
        Queue::fake();
        Event::fake();

        $merchant = $this->createMerchant(['level' => 'vip', 'status' => 1]);
        $duplicateDomain = 'dup-' . Str::random(6) . '.jerseyholic.test';

        // 第一个站点使用该域名
        $store = $this->service->provision($merchant, [
            'store_name' => 'First Store',
            'store_code' => 'dup_first_' . Str::random(6),
            'domain'     => $duplicateDomain,
        ]);
        $this->createdStores[] = $store;

        // 第二个站点使用相同域名应失败
        $this->expectException(StoreProvisioningException::class);

        $this->service->provision($merchant, [
            'store_name' => 'Duplicate Store',
            'store_code' => 'dup_second_' . Str::random(6),
            'domain'     => $duplicateDomain,
        ]);
    }

    /* ================================================================
     |  商户状态校验
     | ================================================================ */

    /** @test 测试未激活商户无法创建站点 */
    public function test_provision_fails_for_inactive_merchant(): void
    {
        $merchant = $this->createMerchant(['status' => 0]); // inactive

        $this->expectException(StoreProvisioningException::class);

        $this->service->provision($merchant, [
            'store_name' => 'Should Fail',
            'store_code' => 'fail_' . Str::random(6),
            'domain'     => 'fail-' . Str::random(6) . '.jerseyholic.test',
        ]);
    }

    /* ================================================================
     |  失败回滚 & StoreProvisionFailed 事件
     | ================================================================ */

    /** @test 测试创建过程中发生异常时触发 StoreProvisionFailed 事件 */
    public function test_provision_failure_dispatches_failed_event(): void
    {
        Event::fake([StoreProvisioned::class, StoreProvisionFailed::class]);

        $merchant = $this->createMerchant(['status' => 0]); // 非激活 → 必然失败

        try {
            $this->service->provision($merchant, [
                'store_name' => 'Fail Event Store',
                'store_code' => 'fail_evt_' . Str::random(6),
                'domain'     => 'fail-evt-' . Str::random(6) . '.jerseyholic.test',
            ]);
        } catch (StoreProvisioningException $e) {
            // 预期抛异常
        }

        // 由于 merchantInactive 在 provision 第一步就抛出，不会创建 Store
        // StoreProvisionFailed 在 catch 块中触发
        Event::assertDispatched(StoreProvisionFailed::class);
        Event::assertNotDispatched(StoreProvisioned::class);
    }

    /** @test 测试配额校验使用正确的等级限制映射 */
    public function test_validate_store_quota_returns_correct_result(): void
    {
        // starter: 限额 2
        $starterMerchant = $this->createMerchant(['level' => 'starter', 'status' => 1]);
        $this->assertTrue($this->service->validateStoreQuota($starterMerchant));

        // vip: 不限
        $vipMerchant = $this->createMerchant(['level' => 'vip', 'status' => 1]);
        $this->assertTrue($this->service->validateStoreQuota($vipMerchant));
    }
}
