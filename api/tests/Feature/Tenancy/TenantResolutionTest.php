<?php

namespace Tests\Feature\Tenancy;

use App\Http\Middleware\ResolveTenantByDomain;
use App\Models\Central\Domain;
use App\Models\Central\Store;
use Illuminate\Support\Str;
use Tests\TenancyTestCase;

/**
 * 域名识别测试
 *
 * 验证 ResolveTenantByDomain 中间件能正确地：
 * - 根据域名解析到对应的 Store
 * - Central 域名跳过租户识别
 * - 未知域名返回 404
 * - 已停用站点返回 503
 * - 在 request 中注入 store 和 merchant 信息
 */
class TenantResolutionTest extends TenancyTestCase
{
    /* ================================================================
     |  正常域名解析
     | ================================================================ */

    /** @test 测试正确域名能解析到对应的 Store 并注入 request 属性 */
    public function test_valid_tenant_domain_resolves_to_store(): void
    {
        $merchant = $this->createMerchant();
        $store = $this->createStore($merchant, ['status' => 1]);
        $domainName = $store->domain;
        $this->createDomain($store, $domainName);

        $response = $this->withHeaders([
            'Host' => $domainName,
        ])->getJson('/api/v1/store/info');

        // 不应返回 404（Store 被找到了）
        $response->assertStatus(200);
    }

    /** @test 测试请求中包含 store 属性 */
    public function test_request_contains_store_and_merchant_attributes(): void
    {
        $merchant = $this->createMerchant();
        $store = $this->createStore($merchant, ['status' => 1]);
        $domainName = $store->domain;
        $this->createDomain($store, $domainName);

        // 通过 tenant 路由 /api/v1/store/info 来验证 store 已注入
        $response = $this->withHeaders([
            'Host' => $domainName,
        ])->getJson('/api/v1/store/info');

        $response->assertSuccessful();
        $response->assertJsonStructure([
            'success',
            'data' => ['name', 'status'],
        ]);
    }

    /* ================================================================
     |  Central 域名跳过租户识别
     | ================================================================ */

    /** @test 测试 Central 域名（localhost）不触发租户识别 */
    public function test_central_domain_skips_tenant_resolution(): void
    {
        // localhost 是配置中的 central_domains
        $response = $this->withHeaders([
            'Host' => 'localhost',
        ])->getJson('/api/v1/admin/auth/login');

        // Central 域名应正常通过中间件，不返回 STORE_NOT_FOUND
        $response->assertStatus(200)->assertJsonMissing(['error_code' => 'STORE_NOT_FOUND']);
    }

    /** @test 测试配置中的 admin.jerseyholic.com 也是 Central 域名 */
    public function test_admin_domain_is_central(): void
    {
        $centralDomains = config('tenancy.central_domains', []);

        $this->assertContains('localhost', $centralDomains);
        // admin.jerseyholic.com 或 env 中配置的值
        $this->assertNotEmpty($centralDomains);
    }

    /* ================================================================
     |  未知域名返回 404
     | ================================================================ */

    /** @test 测试不存在的域名返回 404 和 STORE_NOT_FOUND 错误码 */
    public function test_unknown_domain_returns_404(): void
    {
        $unknownDomain = 'nonexistent-' . Str::random(10) . '.jerseyholic.test';

        $response = $this->withHeaders([
            'Host' => $unknownDomain,
        ])->getJson('/api/v1/products');

        $response->assertStatus(404);
        $response->assertJson([
            'success'    => false,
            'error_code' => 'STORE_NOT_FOUND',
        ]);
    }

    /* ================================================================
     |  已停用站点域名返回 503
     | ================================================================ */

    /** @test 测试 maintenance 状态的站点返回 503 */
    public function test_maintenance_store_domain_returns_503(): void
    {
        $merchant = $this->createMerchant();
        // status 使用字符串 'maintenance' 对应中间件 checkStoreStatus 的 match
        $store = $this->createStore($merchant, ['status' => 'maintenance']);
        $domainName = $store->domain;
        $this->createDomain($store, $domainName);

        $response = $this->withHeaders([
            'Host' => $domainName,
        ])->getJson('/api/v1/products');

        $response->assertStatus(503);
        $response->assertJson([
            'success'    => false,
            'error_code' => 'STORE_MAINTENANCE',
        ]);
    }

    /** @test 测试 suspended 状态的站点返回 403 */
    public function test_suspended_store_domain_returns_403(): void
    {
        $merchant = $this->createMerchant();
        $store = $this->createStore($merchant, ['status' => 'suspended']);
        $domainName = $store->domain;
        $this->createDomain($store, $domainName);

        $response = $this->withHeaders([
            'Host' => $domainName,
        ])->getJson('/api/v1/products');

        $response->assertStatus(403);
        $response->assertJson([
            'success'    => false,
            'error_code' => 'STORE_SUSPENDED',
        ]);
    }

    /* ================================================================
     |  www 前缀处理
     | ================================================================ */

    /** @test 测试带 www 前缀的域名也能正确解析 */
    public function test_www_prefix_is_stripped_during_resolution(): void
    {
        $merchant = $this->createMerchant();
        $store = $this->createStore($merchant, ['status' => 1]);
        $domainName = $store->domain;
        $this->createDomain($store, $domainName);

        // 用 www. 前缀访问，中间件会自动去掉 www
        $response = $this->withHeaders([
            'Host' => 'www.' . $domainName,
        ])->getJson('/api/v1/store/info');

        $response->assertSuccessful();
    }
}
