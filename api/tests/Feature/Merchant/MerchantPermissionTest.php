<?php

namespace Tests\Feature\Merchant;

use App\Http\Middleware\MerchantStoreAccess;
use App\Models\Central\Merchant;
use App\Models\Central\MerchantUser;
use App\Models\Central\Store;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Tests\TenancyTestCase;

/**
 * 商户权限隔离集成测试
 *
 * 覆盖商户间数据隔离、allowed_store_ids 限制、角色权限及 MerchantStoreAccess 中间件功能。
 */
class MerchantPermissionTest extends TenancyTestCase
{
    use RefreshDatabase;

    /** 快速创建一个激活的商户用户 */
    private function makeMerchantUser(
        int $merchantId,
        string $role = 'owner',
        ?array $allowedStoreIds = null
    ): MerchantUser {
        return MerchantUser::create([
            'merchant_id'       => $merchantId,
            'username'          => 'user_' . \Illuminate\Support\Str::random(6),
            'email'             => 'u_' . \Illuminate\Support\Str::random(8) . '@test.com',
            'password'          => Hash::make('Password123!'),
            'name'              => 'Test User',
            'role'              => $role,
            'status'            => 1,
            'allowed_store_ids' => $allowedStoreIds,
            'login_failures'    => 0,
        ]);
    }

    /* ----------------------------------------------------------------
     |  商户间数据隔离
     | ---------------------------------------------------------------- */

    /** 测试商户 A 无法通过 GET /me 看到商户 B 的站点 */
    public function test_merchant_a_cannot_see_merchant_b_stores_in_me(): void
    {
        $merchantA = $this->createMerchant(['status' => 1]);
        $merchantB = $this->createMerchant(['status' => 1]);

        $storeA = $this->createStore($merchantA);
        $storeB = $this->createStore($merchantB);

        $userA = $this->makeMerchantUser($merchantA->id, 'owner');

        $response = $this->actingAs($userA, 'merchant')
            ->getJson('/api/v1/merchant/auth/me');

        $response->assertStatus(200);

        $stores   = $response->json('data.stores');
        $storeIds = array_column($stores ?? [], 'id');

        $this->assertContains($storeA->id, $storeIds, '应能看到自己商户的站点');
        $this->assertNotContains($storeB->id, $storeIds, '不应看到其他商户的站点');
    }

    /* ----------------------------------------------------------------
     |  allowed_store_ids 权限限制
     | ---------------------------------------------------------------- */

    /** 测试 allowed_store_ids 设定后 operator 只能访问指定站点 */
    public function test_operator_with_limited_store_ids_cannot_see_other_stores(): void
    {
        $merchant = $this->createMerchant(['status' => 1]);
        $storeA   = $this->createStore($merchant);
        $storeB   = $this->createStore($merchant);

        // operator 仅允许访问 storeA
        $operator = $this->makeMerchantUser($merchant->id, 'operator', [$storeA->id]);

        $response = $this->actingAs($operator, 'merchant')
            ->getJson('/api/v1/merchant/auth/me');

        $response->assertStatus(200);

        $stores   = $response->json('data.stores');
        $storeIds = array_column($stores ?? [], 'id');

        $this->assertContains($storeA->id, $storeIds, 'operator 应能看到被允许的站点');
        $this->assertNotContains($storeB->id, $storeIds, 'operator 不应看到未被允许的站点');
    }

    /** 测试 owner（allowed_store_ids 为 null）可访问商户名下所有站点 */
    public function test_owner_with_null_allowed_store_ids_can_access_all_stores(): void
    {
        $merchant = $this->createMerchant(['status' => 1]);
        $storeA   = $this->createStore($merchant);
        $storeB   = $this->createStore($merchant);

        // owner 的 allowed_store_ids 为 null，表示全站点访问
        $owner = $this->makeMerchantUser($merchant->id, 'owner', null);

        $response = $this->actingAs($owner, 'merchant')
            ->getJson('/api/v1/merchant/auth/me');

        $response->assertStatus(200);

        $stores   = $response->json('data.stores');
        $storeIds = array_column($stores ?? [], 'id');

        $this->assertContains($storeA->id, $storeIds);
        $this->assertContains($storeB->id, $storeIds);
    }

    /* ----------------------------------------------------------------
     |  MerchantStoreAccess 中间件
     | ---------------------------------------------------------------- */

    /**
     * 测试 MerchantStoreAccess 中间件：访问他人站点返回 403
     */
    public function test_merchant_store_access_middleware_blocks_cross_merchant_access(): void
    {
        $merchantA = $this->createMerchant(['status' => 1]);
        $merchantB = $this->createMerchant(['status' => 1]);
        $storeB    = $this->createStore($merchantB);
        $userA     = $this->makeMerchantUser($merchantA->id, 'owner');

        // 直接实例化中间件并发送一个带有 store_id（属于 merchantB）的请求
        $request = Request::create('/api/test', 'GET');
        $request->query->set('store_id', $storeB->id);

        // 通过 mock auth guard 设置商户用户
        $this->actingAs($userA, 'merchant');
        $request->setUserResolver(fn ($guard = null) => $guard === 'merchant' ? $userA : null);

        $middleware = new MerchantStoreAccess();
        $response   = $middleware->handle($request, fn ($req) => response()->json(['ok' => true]));

        $this->assertEquals(403, $response->getStatusCode());
    }

    /**
     * 测试 MerchantStoreAccess 中间件：访问自身站点放行，且注入 current_store 到请求属性
     */
    public function test_merchant_store_access_middleware_injects_context_for_own_store(): void
    {
        $merchant = $this->createMerchant(['status' => 1]);
        $store    = $this->createStore($merchant);
        $user     = $this->makeMerchantUser($merchant->id, 'owner');

        $request = Request::create('/api/test', 'GET');
        $request->query->set('store_id', $store->id);
        $request->setUserResolver(fn ($guard = null) => $guard === 'merchant' ? $user : null);

        $middleware   = new MerchantStoreAccess();
        $passedRequest = null;

        $response = $middleware->handle($request, function ($req) use (&$passedRequest) {
            $passedRequest = $req;
            return response()->json(['ok' => true]);
        });

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertNotNull($passedRequest->attributes->get('current_store'), 'current_store 应被注入到请求属性中');
        $this->assertNotNull($passedRequest->attributes->get('current_merchant'), 'current_merchant 应被注入到请求属性中');
    }

    /**
     * 测试 MerchantStoreAccess 中间件：operator 访问未被授权的站点返回 403
     */
    public function test_operator_with_limited_access_is_blocked_by_middleware(): void
    {
        $merchant = $this->createMerchant(['status' => 1]);
        $storeA   = $this->createStore($merchant);
        $storeB   = $this->createStore($merchant);

        // operator 仅被允许访问 storeA
        $operator = $this->makeMerchantUser($merchant->id, 'operator', [$storeA->id]);

        $request = Request::create('/api/test', 'GET');
        $request->query->set('store_id', $storeB->id); // 尝试访问未授权的 storeB
        $request->setUserResolver(fn ($guard = null) => $guard === 'merchant' ? $operator : null);

        $middleware = new MerchantStoreAccess();
        $response   = $middleware->handle($request, fn ($req) => response()->json(['ok' => true]));

        $this->assertEquals(403, $response->getStatusCode());
    }

    /**
     * 测试 MerchantStoreAccess 中间件：无 store_id 时直接放行（Dashboard 聚合接口场景）
     */
    public function test_middleware_allows_request_without_store_id(): void
    {
        $merchant = $this->createMerchant(['status' => 1]);
        $user     = $this->makeMerchantUser($merchant->id, 'owner');

        $request = Request::create('/api/merchant/dashboard', 'GET');
        $request->setUserResolver(fn ($guard = null) => $guard === 'merchant' ? $user : null);

        $middleware = new MerchantStoreAccess();
        $response   = $middleware->handle($request, fn ($req) => response()->json(['ok' => true]));

        $this->assertEquals(200, $response->getStatusCode());
    }

    /* ----------------------------------------------------------------
     |  用户列表权限
     | ---------------------------------------------------------------- */

    /** 测试 operator 无权访问用户列表，返回 403 */
    public function test_operator_cannot_list_merchant_users(): void
    {
        $merchant = $this->createMerchant(['status' => 1]);
        $operator = $this->makeMerchantUser($merchant->id, 'operator');

        $response = $this->actingAs($operator, 'merchant')
            ->getJson('/api/v1/merchant/users');

        $response->assertStatus(403);
    }

    /** 测试 owner 可访问用户列表，返回 200 */
    public function test_owner_can_list_merchant_users(): void
    {
        $merchant = $this->createMerchant(['status' => 1]);
        $owner    = $this->makeMerchantUser($merchant->id, 'owner');

        $response = $this->actingAs($owner, 'merchant')
            ->getJson('/api/v1/merchant/users');

        $response->assertStatus(200);
    }

    /** 测试 manager 可访问用户列表，返回 200 */
    public function test_manager_can_list_merchant_users(): void
    {
        $merchant = $this->createMerchant(['status' => 1]);
        $manager  = $this->makeMerchantUser($merchant->id, 'manager');

        $response = $this->actingAs($manager, 'merchant')
            ->getJson('/api/v1/merchant/users');

        $response->assertStatus(200);
    }
}
