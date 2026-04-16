<?php

namespace Tests\Feature\Tenancy;

use Illuminate\Support\Facades\Cache;
use Tests\TenancyTestCase;

/**
 * 缓存隔离测试
 *
 * 验证 stancl/tenancy 的 CacheTenancyBootstrapper 生效后：
 * - 不同租户的缓存 key 使用不同前缀
 * - 租户 A 的缓存在租户 B 上下文中不可见
 */
class CacheIsolationTest extends TenancyTestCase
{
    /* ================================================================
     |  缓存前缀隔离
     | ================================================================ */

    /** @test 测试不同租户使用不同的缓存 key 前缀 */
    public function test_different_tenants_have_different_cache_prefixes(): void
    {
        $merchant = $this->createMerchant();
        $storeA = $this->createStore($merchant);
        $storeB = $this->createStore($merchant);

        $cacheKeyPrefixA = null;
        $cacheKeyPrefixB = null;

        // 在站点 A 上下文中获取缓存前缀
        $this->runInTenantContext($storeA, function () use (&$cacheKeyPrefixA) {
            $cacheKeyPrefixA = config('cache.prefix') ?: Cache::getPrefix();
        });

        // 在站点 B 上下文中获取缓存前缀
        $this->runInTenantContext($storeB, function () use (&$cacheKeyPrefixB) {
            $cacheKeyPrefixB = config('cache.prefix') ?: Cache::getPrefix();
        });

        // 两个租户的缓存前缀应不同
        $this->assertNotEquals($cacheKeyPrefixA, $cacheKeyPrefixB);
    }

    /** @test 测试租户 A 的缓存在租户 B 上下文中不可见 */
    public function test_tenant_a_cache_is_invisible_in_tenant_b_context(): void
    {
        $merchant = $this->createMerchant();
        $storeA = $this->createStore($merchant);
        $storeB = $this->createStore($merchant);

        // 在站点 A 上下文中写入缓存
        $this->runInTenantContext($storeA, function () {
            Cache::put('isolation_test_key', 'value_from_store_a', 300);

            // 在同一上下文中能读取到
            $this->assertEquals('value_from_store_a', Cache::get('isolation_test_key'));
        });

        // 在站点 B 上下文中读取同一 key，应为 null
        $this->runInTenantContext($storeB, function () {
            $value = Cache::get('isolation_test_key');
            $this->assertNull($value, '租户 A 的缓存不应在租户 B 上下文中可见');
        });
    }

    /** @test 测试相同 key 在不同租户中可存储不同值 */
    public function test_same_cache_key_stores_different_values_per_tenant(): void
    {
        $merchant = $this->createMerchant();
        $storeA = $this->createStore($merchant);
        $storeB = $this->createStore($merchant);

        $cacheKey = 'shared_key_name';

        // 在站点 A 中设置值
        $this->runInTenantContext($storeA, function () use ($cacheKey) {
            Cache::put($cacheKey, 'alpha_value', 300);
        });

        // 在站点 B 中设置不同值
        $this->runInTenantContext($storeB, function () use ($cacheKey) {
            Cache::put($cacheKey, 'beta_value', 300);
        });

        // 验证站点 A 的值未被覆盖
        $this->runInTenantContext($storeA, function () use ($cacheKey) {
            $this->assertEquals('alpha_value', Cache::get($cacheKey));
        });

        // 验证站点 B 保持自己的值
        $this->runInTenantContext($storeB, function () use ($cacheKey) {
            $this->assertEquals('beta_value', Cache::get($cacheKey));
        });
    }

    /** @test 测试租户上下文结束后缓存前缀恢复到 Central */
    public function test_cache_prefix_restores_after_tenancy_ends(): void
    {
        $merchant = $this->createMerchant();
        $store = $this->createStore($merchant);

        // 记录 Central 上下文的缓存前缀
        $centralPrefix = config('cache.prefix') ?: Cache::getPrefix();

        // 进入再退出租户上下文
        $this->initializeTenancy($store);
        $tenantPrefix = config('cache.prefix') ?: Cache::getPrefix();
        $this->endTenancy();

        // 退出后应恢复到 Central 前缀
        $restoredPrefix = config('cache.prefix') ?: Cache::getPrefix();

        $this->assertNotEquals($centralPrefix, $tenantPrefix, '租户前缀应与 Central 不同');
        $this->assertEquals($centralPrefix, $restoredPrefix, '退出租户后应恢复 Central 前缀');
    }
}
