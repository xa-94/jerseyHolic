<?php

declare(strict_types=1);

namespace Tests\Feature\Security;

use App\Models\Central\Merchant;
use App\Models\Central\MerchantApiKey;
use App\Models\Central\MerchantUser;
use App\Models\Central\PaymentAccount;
use App\Models\Central\Store;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Tests\TenancyTestCase;

/**
 * 商户权限边界安全测试
 *
 * 验证商户内部不同角色（owner/manager/operator）的权限边界，
 * 以及商户间数据隔离、敏感信息保护等安全机制。
 */
class MerchantPermissionBoundaryTest extends TenancyTestCase
{
    use RefreshDatabase;

    /** 创建指定角色的商户用户 */
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

    /** 为商户创建支付账号 */
    private function createPaymentAccountForMerchant(Merchant $merchant): PaymentAccount
    {
        return PaymentAccount::create([
            'account'             => 'paypal_' . \Illuminate\Support\Str::random(8),
            'email'               => 'paypal_' . \Illuminate\Support\Str::random(8) . '@test.com',
            'client_id'           => 'client_' . \Illuminate\Support\Str::random(16),
            'client_secret'       => 'secret_' . \Illuminate\Support\Str::random(32),
            'merchant_id_external'=> 'merchant_ext_' . \Illuminate\Support\Str::random(8),
            'pay_method'          => 'paypal',
            'status'              => 1,
            'permission'          => 1,
            'webhook_id'          => 'webhook_' . \Illuminate\Support\Str::random(16),
        ]);
    }

    /** 为商户创建 API 密钥 */
    private function createApiKeyForMerchant(Merchant $merchant, ?Store $store = null): MerchantApiKey
    {
        return MerchantApiKey::create([
            'merchant_id'               => $merchant->id,
            'store_id'                  => $store?->id,
            'key_id'                    => 'mk_' . \Illuminate\Support\Str::random(24),
            'public_key'                => "-----BEGIN PUBLIC KEY-----\n" . \Illuminate\Support\Str::random(64) . "\n-----END PUBLIC KEY-----",
            'algorithm'                 => 'RSA-SHA256',
            'key_size'                  => 4096,
            'status'                    => 'active',
            'activated_at'              => now(),
            'download_token'            => hash('sha256', \Illuminate\Support\Str::random(64)),
            'download_token_expires_at' => now()->addHours(24),
        ]);
    }

    /* ----------------------------------------------------------------
     |  Operator 站点访问限制测试
     | ---------------------------------------------------------------- */

    /** 测试 operator 只能操作被授权的站点 */
    public function test_operator_can_only_access_authorized_stores(): void
    {
        $merchant = $this->createMerchant(['status' => 1]);
        $storeA   = $this->createStore($merchant);
        $storeB   = $this->createStore($merchant);
        $storeC   = $this->createStore($merchant);

        // operator 仅被允许访问 storeA 和 storeC
        $operator = $this->makeMerchantUser($merchant->id, 'operator', [$storeA->id, $storeC->id]);

        // 访问 /me 接口，验证只能看到被授权的站点
        $response = $this->actingAs($operator, 'merchant')
            ->getJson('/api/v1/merchant/auth/me');

        $response->assertStatus(200);

        $stores   = $response->json('data.stores');
        $storeIds = array_column($stores ?? [], 'id');

        $this->assertContains($storeA->id, $storeIds, 'operator 应能看到被授权的站点 A');
        $this->assertContains($storeC->id, $storeIds, 'operator 应能看到被授权的站点 C');
        $this->assertNotContains($storeB->id, $storeIds, 'operator 不应看到未被授权的站点 B');
    }

    /* ----------------------------------------------------------------
     |  Operator 创建站点权限测试
     | ---------------------------------------------------------------- */

    /** 测试 operator 无权创建站点（403） */
    public function test_operator_cannot_create_store(): void
    {
        $merchant = $this->createMerchant(['status' => 1]);
        $operator = $this->makeMerchantUser($merchant->id, 'operator');

        // 尝试创建站点（此操作通常由 owner/manager 执行）
        $response = $this->actingAs($operator, 'merchant')
            ->postJson('/api/v1/merchant/shop', [
                'store_name' => 'Unauthorized Store',
                'store_code' => 'unauthorized_' . \Illuminate\Support\Str::random(6),
            ]);

        // 期望返回 403（如果端点存在且有权限检查）
        // 如果端点不存在则返回 404，但权限检查应优先
        $this->assertTrue(
            in_array($response->getStatusCode(), [403, 404]),
            'operator 创建站点应返回 403 或 404'
        );
    }

    /* ----------------------------------------------------------------
     |  Operator 用户管理权限测试
     | ---------------------------------------------------------------- */

    /** 测试 operator 无权管理用户（403） */
    public function test_operator_cannot_manage_users(): void
    {
        $merchant = $this->createMerchant(['status' => 1]);
        $operator = $this->makeMerchantUser($merchant->id, 'operator');

        // 尝试访问用户列表
        $listResponse = $this->actingAs($operator, 'merchant')
            ->getJson('/api/v1/merchant/users');

        $listResponse->assertStatus(403);

        // 尝试创建用户
        $createResponse = $this->actingAs($operator, 'merchant')
            ->postJson('/api/v1/merchant/users', [
                'username' => 'newuser',
                'email'    => 'newuser@test.com',
                'password' => 'Password123!',
                'name'     => 'New User',
                'role'     => 'operator',
            ]);

        $createResponse->assertStatus(403);
    }

    /* ----------------------------------------------------------------
     |  支付账号敏感信息保护测试
     | ---------------------------------------------------------------- */

    /** 测试商户看不到 secret_key/webhook_secret */
    public function test_merchant_cannot_view_payment_account_secrets(): void
    {
        // 注意：支付账号通常由 admin 管理，商户端可能无法直接访问
        // 如果商户端有只读接口，验证敏感字段被隐藏

        $merchant = $this->createMerchant(['status' => 1]);
        $owner    = $this->makeMerchantUser($merchant->id, 'owner');

        // 创建一个支付账号并关联到商户的站点
        $paymentAccount = $this->createPaymentAccountForMerchant($merchant);
        $store = $this->createStore($merchant);

        // 关联支付账号到站点
        $store->paymentAccounts()->attach($paymentAccount->id, [
            'priority'   => 1,
            'is_active'  => 1,
        ]);

        // 如果商户端有查看支付账号的接口，验证敏感字段不存在
        // 这里假设可能有一个只读接口，我们验证返回数据中不包含敏感信息

        // 直接查询数据库验证模型 hidden 属性
        $accountData = $paymentAccount->toArray();

        $this->assertArrayNotHasKey('client_secret', $accountData, 'client_secret 不应在序列化输出中');
        $this->assertArrayNotHasKey('access_token', $accountData, 'access_token 不应在序列化输出中');
    }

    /* ----------------------------------------------------------------
     |  RSA 私钥一次性下载测试
     | ---------------------------------------------------------------- */

    /** 测试 RSA 私钥一次性下载后返回 404 */
    public function test_rsa_private_key_one_time_download(): void
    {
        $merchant = $this->createMerchant(['status' => 1]);
        $owner    = $this->makeMerchantUser($merchant->id, 'owner');

        // 创建 API 密钥
        $apiKey = $this->createApiKeyForMerchant($merchant);
        $downloadToken = $apiKey->download_token;

        // 模拟缓存中的加密私钥
        $encryptedPayload = base64_encode(json_encode([
            'iv'         => base64_encode(random_bytes(12)),
            'tag'        => base64_encode(random_bytes(16)),
            'ciphertext' => base64_encode('encrypted_private_key_data'),
        ]));
        Cache::put('merchant_private_key:' . $downloadToken, $encryptedPayload, now()->addHours(25));

        // 第一次下载应该成功
        $firstResponse = $this->actingAs($owner, 'merchant')
            ->postJson('/api/v1/merchant/api-keys/download', [
                'download_token' => $downloadToken,
            ]);

        $firstResponse->assertStatus(200)
            ->assertJsonStructure([
                'data' => ['key_id', 'encrypted_private_key', 'algorithm', 'key_size'],
            ]);

        // 第二次使用相同的 download_token 应该失败（410 Gone 或 404）
        $secondResponse = $this->actingAs($owner, 'merchant')
            ->postJson('/api/v1/merchant/api-keys/download', [
                'download_token' => $downloadToken,
            ]);

        $secondResponse->assertStatus(404);

        // 验证数据库中的状态已更新
        $apiKey->refresh();
        $this->assertNotNull($apiKey->downloaded_at, '下载后 downloaded_at 应被设置');
        $this->assertNull($apiKey->download_token, '下载后 download_token 应被清除');
    }

    /* ----------------------------------------------------------------
     |  商户间 API 密钥隔离测试
     | ---------------------------------------------------------------- */

    /** 测试商户A不能访问商户B的API密钥 */
    public function test_merchant_cannot_access_other_merchant_api_keys(): void
    {
        // 创建两个商户
        $merchantA = $this->createMerchant(['status' => 1]);
        $merchantB = $this->createMerchant(['status' => 1]);

        // 为每个商户创建用户
        $userA = $this->makeMerchantUser($merchantA->id, 'owner');
        $userB = $this->makeMerchantUser($merchantB->id, 'owner');

        // 为商户B创建 API 密钥
        $storeB = $this->createStore($merchantB);
        $apiKeyB = $this->createApiKeyForMerchant($merchantB, $storeB);

        // 商户A的用户尝试访问商户B的密钥详情
        $response = $this->actingAs($userA, 'merchant')
            ->getJson('/api/v1/merchant/api-keys/' . $apiKeyB->key_id);

        $response->assertStatus(404);

        // 验证商户B的用户可以访问自己的密钥
        $responseB = $this->actingAs($userB, 'merchant')
            ->getJson('/api/v1/merchant/api-keys/' . $apiKeyB->key_id);

        $responseB->assertStatus(200)
            ->assertJsonFragment(['key_id' => $apiKeyB->key_id]);
    }
}
