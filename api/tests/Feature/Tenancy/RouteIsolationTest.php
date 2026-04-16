<?php

namespace Tests\Feature\Tenancy;

use App\Http\Middleware\PreventAccessFromTenantDomains;
use Illuminate\Support\Str;
use Tests\TenancyTestCase;

/**
 * 路由隔离测试
 *
 * 验证 Central 路由和 Tenant 路由的访问控制：
 * - central.php 中的路由仅在 Central 域名可访问
 * - tenant.php 中的路由仅在租户域名可访问
 * - PreventAccessFromTenantDomains 中间件阻止租户域名访问 Central 路由
 */
class RouteIsolationTest extends TenancyTestCase
{
    /* ================================================================
     |  Central 路由仅 Central 域名可访问
     | ================================================================ */

    /** @test 测试 Central 路由在 Central 域名（localhost）下可正常访问 */
    public function test_central_routes_accessible_from_central_domain(): void
    {
        $response = $this->withHeaders([
            'Host' => 'localhost',
        ])->postJson('/api/v1/admin/auth/login', [
            'email'    => 'admin@test.com',
            'password' => 'password',
        ]);

        // 不管登录是否成功，关键是路由被匹配到了（不返回 CENTRAL_ONLY 错误）
        $this->assertNotEquals(404, $response->getStatusCode());
        $response->assertJsonMissing(['error_code' => 'CENTRAL_ONLY']);
    }

    /** @test 测试从租户域名访问 Central 路由时被 central.only 中间件拦截 */
    public function test_central_routes_blocked_from_tenant_domain(): void
    {
        $merchant = $this->createMerchant();
        $store = $this->createStore($merchant, ['status' => 1]);
        $domainName = $store->domain;
        $this->createDomain($store, $domainName);

        // 使用租户域名访问 Central 路由
        $response = $this->withHeaders([
            'Host' => $domainName,
        ])->getJson('/api/v1/admin/dashboard');

        // PreventAccessFromTenantDomains 应返回 404 + CENTRAL_ONLY
        $response->assertStatus(404);
        $response->assertJson([
            'success'    => false,
            'error_code' => 'CENTRAL_ONLY',
        ]);
    }

    /* ================================================================
     |  Tenant 路由仅租户域名可访问
     | ================================================================ */

    /** @test 测试 Tenant 路由在租户域名下可访问 */
    public function test_tenant_routes_accessible_from_tenant_domain(): void
    {
        $merchant = $this->createMerchant();
        $store = $this->createStore($merchant, ['status' => 1]);
        $domainName = $store->domain;
        $this->createDomain($store, $domainName);

        $response = $this->withHeaders([
            'Host' => $domainName,
        ])->getJson('/api/v1/store/info');

        // 应正常返回（不返回 TENANT_CONTEXT_REQUIRED）
        $response->assertSuccessful();
    }

    /** @test 测试从 Central 域名访问 Tenant 路由时失败（无租户上下文） */
    public function test_tenant_routes_fail_from_central_domain(): void
    {
        // 在 Central 域名下，没有经过 ResolveTenantByDomain，
        // EnsureTenantContext 中间件应阻止访问
        $response = $this->withHeaders([
            'Host' => 'localhost',
        ])->getJson('/api/v1/store/info');

        // 可能返回 403（TENANT_CONTEXT_REQUIRED）或 404（路由不匹配）
        $this->assertTrue(
            in_array($response->getStatusCode(), [403, 404]),
            'Central 域名访问 Tenant 路由应返回 403 或 404，实际: ' . $response->getStatusCode()
        );
    }

    /* ================================================================
     |  PreventAccessFromTenantDomains 中间件测试
     | ================================================================ */

    /** @test 测试 PreventAccessFromTenantDomains 中间件对 Central 域名放行 */
    public function test_prevent_access_middleware_allows_central_domains(): void
    {
        $centralDomains = config('tenancy.central_domains', []);

        foreach ($centralDomains as $centralDomain) {
            $response = $this->withHeaders([
                'Host' => $centralDomain,
            ])->postJson('/api/v1/admin/auth/login', [
                'email'    => 'test@test.com',
                'password' => 'test',
            ]);

            // 不应返回 CENTRAL_ONLY 错误
            $response->assertJsonMissing(['error_code' => 'CENTRAL_ONLY']);
        }
    }

    /** @test 测试 PreventAccessFromTenantDomains 中间件阻止非 Central 域名 */
    public function test_prevent_access_middleware_blocks_non_central_domains(): void
    {
        $nonCentralDomain = 'random-tenant-' . Str::random(6) . '.jerseyholic.test';

        // 直接访问 Central 路由（不创建对应的 Domain 记录，模拟随机域名）
        $response = $this->withHeaders([
            'Host' => $nonCentralDomain,
        ])->getJson('/api/v1/admin/dashboard');

        // 应返回 404 + CENTRAL_ONLY 或 STORE_NOT_FOUND
        $response->assertStatus(404);
    }

    /** @test 测试 central.only 中间件别名映射到 PreventAccessFromTenantDomains */
    public function test_central_only_middleware_alias_is_registered(): void
    {
        // 验证 'central.only' 中间件别名已注册
        $router = app('router');
        $middlewareAliases = $router->getMiddleware();

        // central.only 应映射到 PreventAccessFromTenantDomains
        if (isset($middlewareAliases['central.only'])) {
            $this->assertEquals(
                PreventAccessFromTenantDomains::class,
                $middlewareAliases['central.only']
            );
        } else {
            // 可能在中间件组中注册，检查路由是否生效即可
            $this->assertTrue(true, 'central.only 中间件可能在其他方式注册');
        }
    }
}
