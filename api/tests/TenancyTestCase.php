<?php

namespace Tests;

use App\Models\Central\Domain;
use App\Models\Central\Merchant;
use App\Models\Central\Store;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Stancl\Tenancy\Tenancy;

/**
 * 多租户测试基类
 *
 * 提供创建商户、站点的辅助方法，以及在 setUp / tearDown 中
 * 准备和清理 Central / Tenant 数据库的逻辑。
 */
abstract class TenancyTestCase extends TestCase
{
    /**
     * 测试期间创建的 Store 实例列表（用于 tearDown 清理）
     *
     * @var Store[]
     */
    protected array $createdStores = [];

    /**
     * 测试期间创建的 Merchant 实例列表
     *
     * @var Merchant[]
     */
    protected array $createdMerchants = [];

    protected function setUp(): void
    {
        parent::setUp();

        // 确保测试运行在 central 连接上
        config(['database.default' => 'central']);
    }

    protected function tearDown(): void
    {
        // 结束所有租户上下文
        if (app()->bound(Tenancy::class)) {
            try {
                tenancy()->end();
            } catch (\Throwable $e) {
                // 忽略
            }
        }

        // 清理测试创建的 Tenant DB
        foreach ($this->createdStores as $store) {
            try {
                $dbName = $store->database_name;
                if ($dbName) {
                    DB::connection('central')
                        ->statement("DROP DATABASE IF EXISTS `{$dbName}`");
                }
                // 强制删除（跳过软删除）
                $store->domains()->forceDelete();
                $store->forceDelete();
            } catch (\Throwable $e) {
                // 忽略清理失败
            }
        }

        // 清理测试创建的 Merchant
        foreach ($this->createdMerchants as $merchant) {
            try {
                $merchant->forceDelete();
            } catch (\Throwable $e) {
                // 忽略清理失败
            }
        }

        $this->createdStores = [];
        $this->createdMerchants = [];

        parent::tearDown();
    }

    /* ================================================================
     |  辅助方法
     | ================================================================ */

    /**
     * 创建测试商户
     *
     * @param array $overrides 覆盖默认属性
     * @return Merchant
     */
    protected function createMerchant(array $overrides = []): Merchant
    {
        $merchant = Merchant::create(array_merge([
            'merchant_name' => 'Test Merchant ' . Str::random(6),
            'email'         => 'merchant_' . Str::random(8) . '@test.com',
            'password'      => bcrypt('password'),
            'contact_name'  => 'Test Contact',
            'phone'         => '+1234567890',
            'level'         => 'starter',
            'status'        => 1, // active
        ], $overrides));

        $this->createdMerchants[] = $merchant;

        return $merchant;
    }

    /**
     * 创建测试站点（不经过 StoreProvisioningService，直接创建记录）
     *
     * @param Merchant $merchant 所属商户
     * @param array    $overrides 覆盖默认属性
     * @return Store
     */
    protected function createStore(Merchant $merchant, array $overrides = []): Store
    {
        $storeCode = $overrides['store_code'] ?? 'test_' . Str::random(6);
        $domainName = $overrides['domain'] ?? $storeCode . '.jerseyholic.test';

        $store = Store::create(array_merge([
            'merchant_id'       => $merchant->id,
            'store_name'        => 'Test Store ' . Str::random(6),
            'store_code'        => $storeCode,
            'domain'            => $domainName,
            'status'            => 1, // active
            'database_name'     => null,
            'database_password' => Str::random(32),
        ], $overrides));

        // 生成 database_name（与 StoreProvisioningService 一致）
        if (!$store->database_name) {
            $prefix = config('tenancy.database.prefix', 'store_');
            $suffix = config('tenancy.database.suffix', '');
            $store->update(['database_name' => $prefix . $store->getTenantKey() . $suffix]);
        }

        $this->createdStores[] = $store;

        return $store;
    }

    /**
     * 为站点创建域名记录
     *
     * @param Store  $store
     * @param string $domainName
     * @return Domain
     */
    protected function createDomain(Store $store, string $domainName): Domain
    {
        return $store->domains()->create([
            'domain'             => $domainName,
            'certificate_status' => Domain::CERT_PENDING,
        ]);
    }

    /**
     * 初始化租户上下文
     *
     * @param Store $store
     */
    protected function initializeTenancy(Store $store): void
    {
        tenancy()->initialize($store);
    }

    /**
     * 结束当前租户上下文
     */
    protected function endTenancy(): void
    {
        tenancy()->end();
    }

    /**
     * 在指定 Store 的上下文中执行回调
     *
     * @param Store    $store
     * @param callable $callback
     * @return mixed
     */
    protected function runInTenantContext(Store $store, callable $callback): mixed
    {
        return $store->run($callback);
    }
}
