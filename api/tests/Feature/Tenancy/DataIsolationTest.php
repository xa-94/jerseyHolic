<?php

namespace Tests\Feature\Tenancy;

use App\Models\Central\Merchant;
use App\Models\Central\Store;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TenancyTestCase;

/**
 * 数据隔离测试
 *
 * 验证不同租户之间的数据完全隔离：
 * - 租户 A 的数据在租户 B 的上下文中不可见
 * - Central 数据在所有上下文中均可访问
 */
class DataIsolationTest extends TenancyTestCase
{
    /* ================================================================
     |  租户数据隔离
     | ================================================================ */

    /** @test 测试不同租户的数据完全隔离 — 各自只能看到自己的数据 */
    public function test_tenant_data_is_isolated_between_stores(): void
    {
        $merchant = $this->createMerchant();

        // 创建两个站点
        $storeA = $this->createStore($merchant);
        $storeB = $this->createStore($merchant);

        // 在站点 A 中插入数据
        $this->runInTenantContext($storeA, function () {
            DB::table('settings')->insert([
                'key'   => 'store_name',
                'value' => 'Store A Name',
            ]);
        });

        // 在站点 B 中插入数据
        $this->runInTenantContext($storeB, function () {
            DB::table('settings')->insert([
                'key'   => 'store_name',
                'value' => 'Store B Name',
            ]);
        });

        // 验证站点 A 只能看到自己的数据
        $this->runInTenantContext($storeA, function () {
            $setting = DB::table('settings')->where('key', 'store_name')->first();
            $this->assertNotNull($setting);
            $this->assertEquals('Store A Name', $setting->value);

            // 不应看到站点 B 的数据
            $allSettings = DB::table('settings')->where('key', 'store_name')->get();
            $this->assertCount(1, $allSettings);
        });

        // 验证站点 B 只能看到自己的数据
        $this->runInTenantContext($storeB, function () {
            $setting = DB::table('settings')->where('key', 'store_name')->first();
            $this->assertNotNull($setting);
            $this->assertEquals('Store B Name', $setting->value);

            $allSettings = DB::table('settings')->where('key', 'store_name')->get();
            $this->assertCount(1, $allSettings);
        });
    }

    /** @test 测试切换上下文后数据库连接确实切换到对应的租户数据库 */
    public function test_database_connection_switches_on_context_change(): void
    {
        $merchant = $this->createMerchant();
        $storeA = $this->createStore($merchant);
        $storeB = $this->createStore($merchant);

        // 在站点 A 上下文中，默认连接应指向 A 的数据库
        $this->runInTenantContext($storeA, function () use ($storeA) {
            $currentDb = DB::connection()->getDatabaseName();
            $this->assertStringContainsString((string) $storeA->getTenantKey(), $currentDb);
        });

        // 在站点 B 上下文中，默认连接应指向 B 的数据库
        $this->runInTenantContext($storeB, function () use ($storeB) {
            $currentDb = DB::connection()->getDatabaseName();
            $this->assertStringContainsString((string) $storeB->getTenantKey(), $currentDb);
        });
    }

    /* ================================================================
     |  Central 数据全局共享
     | ================================================================ */

    /** @test 测试 Central DB 的数据在任何上下文中都可访问 */
    public function test_central_data_is_accessible_from_any_context(): void
    {
        $merchant = $this->createMerchant(['merchant_name' => 'Global Visible Merchant']);
        $store = $this->createStore($merchant);

        // 在租户上下文中查询 Central 数据（使用 Central Model）
        $this->runInTenantContext($store, function () use ($merchant) {
            $found = Merchant::find($merchant->id);
            $this->assertNotNull($found);
            $this->assertEquals('Global Visible Merchant', $found->merchant_name);
        });
    }

    /** @test 测试在租户上下文中 Central 连接独立于租户连接 */
    public function test_central_connection_is_independent_in_tenant_context(): void
    {
        $merchant = $this->createMerchant();
        $store = $this->createStore($merchant);

        $this->runInTenantContext($store, function () {
            // Central 连接应始终指向 Central DB
            $centralDb = DB::connection('central')->getDatabaseName();
            $tenantDb  = DB::connection()->getDatabaseName();

            $this->assertNotEquals($centralDb, $tenantDb);
        });
    }

    /** @test 测试两个租户之间的 Store 记录互相可见（通过 Central 连接） */
    public function test_store_records_visible_from_central_across_tenants(): void
    {
        $merchant = $this->createMerchant();
        $storeA = $this->createStore($merchant, ['store_name' => 'Store Alpha']);
        $storeB = $this->createStore($merchant, ['store_name' => 'Store Beta']);

        // 在站点 A 的上下文中，通过 Central Model 可以看到站点 B
        $this->runInTenantContext($storeA, function () use ($storeB) {
            $found = Store::find($storeB->id);
            $this->assertNotNull($found);
            $this->assertEquals('Store Beta', $found->store_name);
        });
    }
}
