<?php

declare(strict_types=1);

namespace Tests\Feature\Security;

use App\Models\Central\Admin;
use App\Models\Central\Merchant;
use App\Models\Central\MerchantUser;
use App\Models\Tenant\Customer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\PersonalAccessToken;
use Tests\TenancyTestCase;

/**
 * Guard 隔离安全测试
 *
 * 验证三套 guard（admin/merchant/customer）之间的 Token 隔离性，
 * 确保跨 guard 访问会被正确拒绝（401）。
 */
class AuthGuardIsolationTest extends TenancyTestCase
{
    use RefreshDatabase;

    /** 创建测试用的 Admin 用户 */
    private function createAdmin(array $overrides = []): Admin
    {
        return Admin::create(array_merge([
            'username' => 'admin_' . \Illuminate\Support\Str::random(6),
            'email'    => 'admin_' . \Illuminate\Support\Str::random(8) . '@test.com',
            'password' => Hash::make('AdminPass123!'),
            'name'     => 'Test Admin',
            'status'   => 1,
            'is_super' => 0,
        ], $overrides));
    }

    /** 创建测试用的 MerchantUser */
    private function createMerchantUser(array $overrides = []): MerchantUser
    {
        $merchant = $this->createMerchant(['status' => 1]);

        return MerchantUser::create(array_merge([
            'merchant_id'    => $merchant->id,
            'username'       => 'merchant_user_' . \Illuminate\Support\Str::random(6),
            'email'          => 'merchant_' . \Illuminate\Support\Str::random(8) . '@test.com',
            'password'       => Hash::make('MerchantPass123!'),
            'name'           => 'Test Merchant User',
            'role'           => 'owner',
            'status'         => 1,
            'login_failures' => 0,
        ], $overrides));
    }

    /** 创建测试用的 Customer（买家） */
    private function createCustomer(Merchant $merchant, array $overrides = []): Customer
    {
        // Customer 存储在租户数据库中，需要先初始化 tenancy
        $store = $this->createStore($merchant);
        $this->initializeTenancy($store);

        $customer = Customer::create(array_merge([
            'firstname'        => 'Test',
            'lastname'         => 'Customer',
            'email'            => 'customer_' . \Illuminate\Support\Str::random(8) . '@test.com',
            'password'         => Hash::make('CustomerPass123!'),
            'status'           => 1,
            'language_code'    => 'en',
            'currency_code'    => 'USD',
            'login_failures'   => 0,
        ], $overrides));

        $this->endTenancy();

        return $customer;
    }

    /* ----------------------------------------------------------------
     |  Admin Token 访问 Merchant 端点测试
     | ---------------------------------------------------------------- */

    /** 测试 admin guard Token 访问 /api/v1/merchant/* 返回 401 */
    public function test_admin_token_cannot_access_merchant_endpoints(): void
    {
        $admin = $this->createAdmin();
        $token = $admin->createToken('admin-token', ['*'])->plainTextToken;

        $response = $this->withToken($token)
            ->getJson('/api/v1/merchant/auth/me');

        $response->assertStatus(401);
    }

    /* ----------------------------------------------------------------
     |  Merchant Token 访问 Admin 端点测试
     | ---------------------------------------------------------------- */

    /** 测试 merchant Token 访问 /api/v1/admin/* 返回 401 */
    public function test_merchant_token_cannot_access_admin_endpoints(): void
    {
        $merchantUser = $this->createMerchantUser();
        $token = $merchantUser->createToken('merchant-token', ['*'])->plainTextToken;

        $response = $this->withToken($token)
            ->getJson('/api/v1/admin/auth/me');

        $response->assertStatus(401);
    }

    /* ----------------------------------------------------------------
     |  Customer Token 访问 Admin/Merchant 端点测试
     | ---------------------------------------------------------------- */

    /** 测试 customer Token 访问 admin/merchant 端点返回 401 */
    public function test_customer_token_cannot_access_admin_or_merchant(): void
    {
        $merchant = $this->createMerchant(['status' => 1]);
        $customer = $this->createCustomer($merchant);

        // 为 customer 创建 token（使用 sanctum guard）
        $token = $customer->createToken('customer-token', ['*'])->plainTextToken;

        // 尝试访问 admin 端点
        $adminResponse = $this->withToken($token)
            ->getJson('/api/v1/admin/auth/me');
        $adminResponse->assertStatus(401);

        // 尝试访问 merchant 端点
        $merchantResponse = $this->withToken($token)
            ->getJson('/api/v1/merchant/auth/me');
        $merchantResponse->assertStatus(401);
    }

    /* ----------------------------------------------------------------
     |  过期 Token 测试
     | ---------------------------------------------------------------- */

    /** 测试过期 Token 返回 401 */
    public function test_expired_token_is_rejected(): void
    {
        $merchantUser = $this->createMerchantUser();

        // 创建一个已过期（过去时间）的 token
        $token = $merchantUser->createToken('expired-token', ['*'])->plainTextToken;

        // 手动将 token 的过期时间设置为过去
        $tokenParts = explode('|', $token);
        $tokenId = $tokenParts[0] ?? null;

        if ($tokenId) {
            PersonalAccessToken::where('id', $tokenId)->update([
                'expires_at' => now()->subDay(),
            ]);
        }

        $response = $this->withToken($token)
            ->getJson('/api/v1/merchant/auth/me');

        $response->assertStatus(401);
    }

    /* ----------------------------------------------------------------
     |  非法 Token 格式测试
     | ---------------------------------------------------------------- */

    /** 测试非法格式 Token 返回 401 */
    public function test_invalid_token_format_rejected(): void
    {
        // 测试各种非法格式的 token
        $invalidTokens = [
            'invalid-token-format',
            'Bearer invalid',
            'token_without_pipe_separator',
            '123|', // 空 token 部分
            '|abc', // 空 ID 部分
            '',     // 空字符串
            'abc|def|ghi', // 过多分隔符
        ];

        foreach ($invalidTokens as $invalidToken) {
            $response = $this->withToken($invalidToken)
                ->getJson('/api/v1/merchant/auth/me');

            $response->assertStatus(401);
        }
    }

    /* ----------------------------------------------------------------
     |  Token 跨 Guard 不可用测试
     | ---------------------------------------------------------------- */

    /** 测试 Token 跨 guard 不可用 */
    public function test_token_from_one_guard_not_valid_in_another(): void
    {
        // 创建三个不同 guard 的用户
        $admin = $this->createAdmin();
        $merchantUser = $this->createMerchantUser();
        $merchant = $this->createMerchant(['status' => 1]);
        $customer = $this->createCustomer($merchant);

        // 为每个用户创建 token
        $adminToken = $admin->createToken('admin-token', ['*'])->plainTextToken;
        $merchantToken = $merchantUser->createToken('merchant-token', ['*'])->plainTextToken;
        $customerToken = $customer->createToken('customer-token', ['*'])->plainTextToken;

        // Admin token 只能访问 admin 端点
        $this->withToken($adminToken)
            ->getJson('/api/v1/admin/dashboard')
            ->assertStatus(200);

        // Merchant token 只能访问 merchant 端点
        $this->withToken($merchantToken)
            ->getJson('/api/v1/merchant/auth/me')
            ->assertStatus(200);

        // 验证跨 guard 访问都被拒绝
        // Admin token 访问 merchant
        $this->withToken($adminToken)
            ->getJson('/api/v1/merchant/auth/me')
            ->assertStatus(401);

        // Merchant token 访问 admin
        $this->withToken($merchantToken)
            ->getJson('/api/v1/admin/dashboard')
            ->assertStatus(401);

        // Customer token 访问 admin 和 merchant
        $this->withToken($customerToken)
            ->getJson('/api/v1/admin/dashboard')
            ->assertStatus(401);
        $this->withToken($customerToken)
            ->getJson('/api/v1/merchant/auth/me')
            ->assertStatus(401);
    }
}
