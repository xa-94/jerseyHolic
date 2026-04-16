<?php

namespace Tests\Feature\Merchant;

use App\Models\Central\Merchant;
use App\Models\Central\MerchantUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TenancyTestCase;

/**
 * 商户认证集成测试
 *
 * 覆盖商户用户的登录、Token 管理、账户锁定及 /me 接口功能。
 */
class MerchantAuthTest extends TenancyTestCase
{
    use RefreshDatabase;

    /** 创建一个可登录的商户用户 */
    private function createMerchantUser(array $overrides = []): MerchantUser
    {
        $merchant = $this->createMerchant(['status' => 1]);

        return MerchantUser::create(array_merge([
            'merchant_id'    => $merchant->id,
            'username'       => 'testuser_' . \Illuminate\Support\Str::random(6),
            'email'          => 'user_' . \Illuminate\Support\Str::random(8) . '@test.com',
            'password'       => Hash::make('Password123!'),
            'name'           => 'Test User',
            'role'           => 'owner',
            'status'         => 1,
            'login_failures' => 0,
        ], $overrides));
    }

    /* ----------------------------------------------------------------
     |  登录（email）
     | ---------------------------------------------------------------- */

    /** 测试商户用户使用 email 登录成功，返回 Sanctum Token */
    public function test_merchant_user_can_login_with_email(): void
    {
        $user = $this->createMerchantUser(['email' => 'email_login@test.com']);

        $response = $this->postJson('/api/v1/merchant/auth/login', [
            'login'    => 'email_login@test.com',
            'password' => 'Password123!',
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => ['token', 'token_type', 'user', 'merchant'],
            ])
            ->assertJsonFragment(['token_type' => 'Bearer']);
    }

    /** 测试商户用户使用 username 登录成功，返回 Sanctum Token */
    public function test_merchant_user_can_login_with_username(): void
    {
        $user = $this->createMerchantUser(['username' => 'myusername123']);

        $response = $this->postJson('/api/v1/merchant/auth/login', [
            'login'    => 'myusername123',
            'password' => 'Password123!',
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => ['token', 'token_type'],
            ]);
    }

    /** 测试登录成功后 token 字段非空 */
    public function test_login_returns_non_empty_sanctum_token(): void
    {
        $user = $this->createMerchantUser(['email' => 'token_check@test.com']);

        $response = $this->postJson('/api/v1/merchant/auth/login', [
            'login'    => 'token_check@test.com',
            'password' => 'Password123!',
        ]);

        $token = $response->json('data.token');
        $this->assertNotEmpty($token);
        $this->assertStringContainsString('|', $token); // Sanctum token 格式 id|token
    }

    /** 测试错误密码登录返回 401 */
    public function test_login_with_wrong_password_returns_401(): void
    {
        $this->createMerchantUser(['email' => 'wrong_pass@test.com']);

        $response = $this->postJson('/api/v1/merchant/auth/login', [
            'login'    => 'wrong_pass@test.com',
            'password' => 'WrongPassword!',
        ]);

        $response->assertStatus(401);
    }

    /** 测试不存在的账号登录返回 401 */
    public function test_login_with_nonexistent_account_returns_401(): void
    {
        $response = $this->postJson('/api/v1/merchant/auth/login', [
            'login'    => 'nonexistent@test.com',
            'password' => 'Password123!',
        ]);

        $response->assertStatus(401);
    }

    /* ----------------------------------------------------------------
     |  账户锁定
     | ---------------------------------------------------------------- */

    /** 测试连续登录失败 5 次后账户被锁定 */
    public function test_account_is_locked_after_5_failed_logins(): void
    {
        $user = $this->createMerchantUser(['email' => 'lockme@test.com']);

        // 失败 5 次
        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/v1/merchant/auth/login', [
                'login'    => 'lockme@test.com',
                'password' => 'WrongPassword!',
            ]);
        }

        $user->refresh();
        $this->assertNotNull($user->locked_until, '账户应在 5 次失败后被锁定');
        $this->assertTrue($user->locked_until->isFuture(), 'locked_until 应为未来时间');
    }

    /** 测试账户锁定期间无法登录，返回 423 */
    public function test_locked_account_cannot_login_returns_423(): void
    {
        $user = $this->createMerchantUser([
            'email'          => 'locked@test.com',
            'login_failures' => 5,
            'locked_until'   => now()->addMinutes(15),
        ]);

        $response = $this->postJson('/api/v1/merchant/auth/login', [
            'login'    => 'locked@test.com',
            'password' => 'Password123!',
        ]);

        $response->assertStatus(423);
    }

    /** 测试锁定时间过期后可重新登录 */
    public function test_expired_lock_allows_login(): void
    {
        $user = $this->createMerchantUser([
            'email'          => 'expired_lock@test.com',
            'login_failures' => 5,
            'locked_until'   => now()->subMinute(), // 已过期
        ]);

        $response = $this->postJson('/api/v1/merchant/auth/login', [
            'login'    => 'expired_lock@test.com',
            'password' => 'Password123!',
        ]);

        $response->assertStatus(200);
    }

    /* ----------------------------------------------------------------
     |  登出
     | ---------------------------------------------------------------- */

    /** 测试登出成功撤销 Token，之后访问保护接口返回 401 */
    public function test_logout_revokes_token(): void
    {
        $user = $this->createMerchantUser(['email' => 'logout_test@test.com']);

        // 先登录获取 token
        $loginResponse = $this->postJson('/api/v1/merchant/auth/login', [
            'login'    => 'logout_test@test.com',
            'password' => 'Password123!',
        ]);

        $token = $loginResponse->json('data.token');

        // 执行登出
        $logoutResponse = $this->withToken($token)
            ->postJson('/api/v1/merchant/auth/logout');

        $logoutResponse->assertStatus(200);

        // 登出后 Token 已撤销，访问 /me 返回 401
        $meResponse = $this->withToken($token)
            ->getJson('/api/v1/merchant/auth/me');

        $meResponse->assertStatus(401);
    }

    /* ----------------------------------------------------------------
     |  GET /me
     | ---------------------------------------------------------------- */

    /** 测试 /me 接口返回当前用户信息和所属商户信息 */
    public function test_me_returns_current_user_and_merchant_info(): void
    {
        $user = $this->createMerchantUser(['email' => 'me_test@test.com']);

        $response = $this->actingAs($user, 'merchant')
            ->getJson('/api/v1/merchant/auth/me');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    'user'     => ['id', 'merchant_id', 'username', 'email', 'role'],
                    'merchant' => ['id', 'merchant_name', 'level', 'status'],
                    'stores',
                ],
            ])
            ->assertJsonFragment(['id' => $user->id])
            ->assertJsonFragment(['merchant_id' => $user->merchant_id]);
    }

    /** 测试未登录访问 /me 返回 401 */
    public function test_me_without_auth_returns_401(): void
    {
        $response = $this->getJson('/api/v1/merchant/auth/me');

        $response->assertStatus(401);
    }

    /** 测试 /me 返回的 stores 列表仅包含用户有权访问的站点 */
    public function test_me_returns_only_accessible_stores(): void
    {
        $merchant = $this->createMerchant(['status' => 1]);
        $storeA   = $this->createStore($merchant);
        $storeB   = $this->createStore($merchant);

        // 创建一个仅能访问 storeA 的 operator
        $user = MerchantUser::create([
            'merchant_id'       => $merchant->id,
            'username'          => 'limited_op',
            'email'             => 'limited@test.com',
            'password'          => Hash::make('Password123!'),
            'name'              => 'Limited Operator',
            'role'              => 'operator',
            'status'            => 1,
            'allowed_store_ids' => [$storeA->id],
            'login_failures'    => 0,
        ]);

        $response = $this->actingAs($user, 'merchant')
            ->getJson('/api/v1/merchant/auth/me');

        $response->assertStatus(200);

        $stores = $response->json('data.stores');
        $storeIds = array_column($stores ?? [], 'id');
        $this->assertContains($storeA->id, $storeIds);
        $this->assertNotContains($storeB->id, $storeIds);
    }

    /* ----------------------------------------------------------------
     |  Token 刷新
     | ---------------------------------------------------------------- */

    /** 测试 Token 刷新后返回新 Token，旧 Token 失效 */
    public function test_token_refresh_invalidates_old_token(): void
    {
        $user = $this->createMerchantUser(['email' => 'refresh_test@test.com']);

        // 登录获取旧 token
        $loginResponse = $this->postJson('/api/v1/merchant/auth/login', [
            'login'    => 'refresh_test@test.com',
            'password' => 'Password123!',
        ]);

        $oldToken = $loginResponse->json('data.token');

        // 刷新 token
        $refreshResponse = $this->withToken($oldToken)
            ->postJson('/api/v1/merchant/auth/refresh');

        $refreshResponse->assertStatus(200)
            ->assertJsonStructure(['data' => ['token', 'token_type']]);

        $newToken = $refreshResponse->json('data.token');
        $this->assertNotEmpty($newToken);
        $this->assertNotEquals($oldToken, $newToken, '刷新后应返回新 Token');

        // 旧 Token 应已失效
        $oldTokenResponse = $this->withToken($oldToken)
            ->getJson('/api/v1/merchant/auth/me');

        $oldTokenResponse->assertStatus(401);
    }

    /** 测试禁用用户无法登录，返回 403 */
    public function test_disabled_user_cannot_login(): void
    {
        $user = $this->createMerchantUser([
            'email'  => 'disabled@test.com',
            'status' => 0, // 禁用
        ]);

        $response = $this->postJson('/api/v1/merchant/auth/login', [
            'login'    => 'disabled@test.com',
            'password' => 'Password123!',
        ]);

        $response->assertStatus(403);
    }
}
