<?php

namespace Tests\Feature\Merchant;

use App\Models\Central\Merchant;
use App\Models\Central\MerchantApiKey;
use App\Models\Central\MerchantUser;
use App\Services\MerchantKeyService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TenancyTestCase;

/**
 * RSA 密钥管理集成测试
 *
 * 覆盖密钥对生成、列表、详情、一次性私钥下载、密钥轮换和吊销功能。
 * 由于生成 RSA-4096 耗时较长，download 相关测试采用直接写库 + Cache 方式模拟。
 */
class ApiKeyTest extends TenancyTestCase
{
    use RefreshDatabase;

    private MerchantUser $owner;
    private Merchant $merchant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->merchant = $this->createMerchant(['status' => 1]);

        $this->owner = MerchantUser::create([
            'merchant_id'    => $this->merchant->id,
            'username'       => 'key_owner',
            'email'          => 'key_owner@test.com',
            'password'       => Hash::make('Password123!'),
            'name'           => 'Key Owner',
            'role'           => 'owner',
            'status'         => 1,
            'login_failures' => 0,
        ]);
    }

    /** 创建一个模拟的 MerchantApiKey 记录（不调用真实 openssl） */
    private function createFakeApiKey(array $overrides = []): MerchantApiKey
    {
        return MerchantApiKey::create(array_merge([
            'merchant_id'               => $this->merchant->id,
            'key_id'                    => 'mk_' . Str::random(24),
            'public_key'                => "-----BEGIN PUBLIC KEY-----\nMOCKPUBLICKEY123\n-----END PUBLIC KEY-----",
            'algorithm'                 => 'RSA-SHA256',
            'key_size'                  => 4096,
            'status'                    => 'active',
            'activated_at'              => now(),
            'download_token'            => hash('sha256', Str::random(64)),
            'download_token_expires_at' => now()->addHours(24),
        ], $overrides));
    }

    /* ----------------------------------------------------------------
     |  密钥生成
     | ---------------------------------------------------------------- */

    /**
     * 测试生成密钥对接口返回 download_token 和 key_id
     *
     * 注意：此测试会调用真实 openssl（RSA-4096），可能耗时较长（约 2-5s）。
     * 如果 openssl 不可用，则测试会跳过。
     */
    public function test_generate_key_pair_returns_download_token(): void
    {
        if (!function_exists('openssl_pkey_new')) {
            $this->markTestSkipped('openssl 扩展不可用，跳过密钥生成测试');
        }

        $response = $this->actingAs($this->owner, 'merchant')
            ->postJson('/api/v1/merchant/api-keys');

        $response->assertStatus(201)
            ->assertJsonStructure([
                'data' => ['key_id', 'download_token', 'download_url', 'expires_in'],
            ]);

        $keyId = $response->json('data.key_id');
        $this->assertStringStartsWith('mk_', $keyId);
    }

    /** 测试未认证访问密钥生成接口返回 401 */
    public function test_unauthenticated_cannot_generate_key(): void
    {
        $response = $this->postJson('/api/v1/merchant/api-keys');

        $response->assertStatus(401);
    }

    /* ----------------------------------------------------------------
     |  密钥列表
     | ---------------------------------------------------------------- */

    /** 测试密钥列表接口返回商户所有密钥（不含私钥） */
    public function test_list_keys_returns_all_merchant_keys(): void
    {
        $key1 = $this->createFakeApiKey();
        $key2 = $this->createFakeApiKey(['status' => 'rotating']);

        $response = $this->actingAs($this->owner, 'merchant')
            ->getJson('/api/v1/merchant/api-keys');

        $response->assertStatus(200)
            ->assertJsonStructure(['data' => ['list']]);

        $list = $response->json('data.list');
        $keyIds = array_column($list, 'key_id');

        $this->assertContains($key1->key_id, $keyIds);
        $this->assertContains($key2->key_id, $keyIds);
    }

    /** 测试密钥列表不包含私钥字段（download_token 应被隐藏） */
    public function test_list_keys_does_not_expose_private_key_or_download_token(): void
    {
        $this->createFakeApiKey();

        $response = $this->actingAs($this->owner, 'merchant')
            ->getJson('/api/v1/merchant/api-keys');

        $response->assertStatus(200);

        $responseBody = $response->getContent();
        $this->assertStringNotContainsString('private_key', $responseBody);
        $this->assertStringNotContainsString('download_token', $responseBody);
    }

    /* ----------------------------------------------------------------
     |  密钥详情
     | ---------------------------------------------------------------- */

    /** 测试密钥详情接口返回指定密钥信息（不含私钥） */
    public function test_show_key_returns_key_details_without_private_key(): void
    {
        $key = $this->createFakeApiKey();

        $response = $this->actingAs($this->owner, 'merchant')
            ->getJson("/api/v1/merchant/api-keys/{$key->key_id}");

        $response->assertStatus(200)
            ->assertJsonFragment(['key_id' => $key->key_id])
            ->assertJsonStructure([
                'data' => ['key_id', 'algorithm', 'key_size', 'status', 'public_key'],
            ]);
    }

    /** 测试访问其他商户的密钥返回 404（权限隔离） */
    public function test_cannot_access_other_merchants_key(): void
    {
        $otherMerchant = $this->createMerchant(['status' => 1]);
        $otherKey = MerchantApiKey::create([
            'merchant_id'  => $otherMerchant->id,
            'key_id'       => 'mk_other_' . Str::random(16),
            'public_key'   => "-----BEGIN PUBLIC KEY-----\nOTHER\n-----END PUBLIC KEY-----",
            'algorithm'    => 'RSA-SHA256',
            'key_size'     => 4096,
            'status'       => 'active',
            'activated_at' => now(),
        ]);

        $response = $this->actingAs($this->owner, 'merchant')
            ->getJson("/api/v1/merchant/api-keys/{$otherKey->key_id}");

        $response->assertStatus(404);
    }

    /* ----------------------------------------------------------------
     |  私钥下载（一次性）
     | ---------------------------------------------------------------- */

    /** 测试凭有效 download_token 可下载一次加密私钥 */
    public function test_can_download_private_key_with_valid_token(): void
    {
        $token  = hash('sha256', Str::random(64));
        $key    = $this->createFakeApiKey([
            'download_token'            => $token,
            'download_token_expires_at' => now()->addHours(24),
            'downloaded_at'             => null,
        ]);

        // 在 cache 中放入模拟的加密私钥载荷
        $fakePayload = base64_encode(json_encode([
            'iv'         => base64_encode(random_bytes(12)),
            'tag'        => base64_encode(random_bytes(16)),
            'ciphertext' => base64_encode('MOCK_ENCRYPTED_PRIVATE_KEY'),
        ]));
        Cache::put('merchant_private_key:' . $token, $fakePayload, now()->addHours(25));

        $response = $this->actingAs($this->owner, 'merchant')
            ->postJson('/api/v1/merchant/api-keys/download', [
                'download_token' => $token,
            ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => ['key_id', 'encrypted_private_key', 'algorithm', 'key_size'],
            ]);

        // 下载后 downloaded_at 应被写入
        $key->refresh();
        $this->assertNotNull($key->downloaded_at, 'downloaded_at 应在下载后被写入');
    }

    /** 测试重复下载同一 download_token 失败，返回 410 */
    public function test_duplicate_download_fails_with_410(): void
    {
        $token = hash('sha256', Str::random(64));
        $this->createFakeApiKey([
            'download_token'            => null,   // 已被清空（已下载）
            'download_token_expires_at' => now()->addHours(24),
            'downloaded_at'             => now(),  // 标记已下载
        ]);

        // 找一个 downloaded_at != null 的 key，模拟重复下载
        $key = $this->createFakeApiKey([
            'download_token'            => $token,
            'download_token_expires_at' => now()->addHours(24),
            'downloaded_at'             => now(), // 已经下载过
        ]);

        $response = $this->actingAs($this->owner, 'merchant')
            ->postJson('/api/v1/merchant/api-keys/download', [
                'download_token' => $token,
            ]);

        $response->assertStatus(410);
    }

    /** 测试过期的 download_token 下载失败，返回 410 */
    public function test_expired_download_token_fails_with_410(): void
    {
        $token = hash('sha256', Str::random(64));
        $this->createFakeApiKey([
            'download_token'            => $token,
            'download_token_expires_at' => now()->subHour(), // 已过期
            'downloaded_at'             => null,
        ]);

        $response = $this->actingAs($this->owner, 'merchant')
            ->postJson('/api/v1/merchant/api-keys/download', [
                'download_token' => $token,
            ]);

        $response->assertStatus(410);
    }

    /** 测试无效 download_token 下载失败，返回 404 */
    public function test_invalid_download_token_returns_404(): void
    {
        $fakeToken = str_repeat('a', 64); // 不存在的 token

        $response = $this->actingAs($this->owner, 'merchant')
            ->postJson('/api/v1/merchant/api-keys/download', [
                'download_token' => $fakeToken,
            ]);

        $response->assertStatus(404);
    }

    /* ----------------------------------------------------------------
     |  密钥轮换
     | ---------------------------------------------------------------- */

    /**
     * 测试密钥轮换后旧密钥变为 rotating，新密钥被生成
     *
     * 此测试会调用真实 openssl，如不可用则跳过。
     */
    public function test_rotate_key_creates_new_key_and_sets_old_to_rotating(): void
    {
        if (!function_exists('openssl_pkey_new')) {
            $this->markTestSkipped('openssl 扩展不可用，跳过密钥轮换测试');
        }

        $oldKey = $this->createFakeApiKey();

        $response = $this->actingAs($this->owner, 'merchant')
            ->postJson("/api/v1/merchant/api-keys/{$oldKey->key_id}/rotate");

        $response->assertStatus(201)
            ->assertJsonStructure([
                'data' => ['key_id', 'download_token'],
            ]);

        // 旧密钥应变为 rotating
        $oldKey->refresh();
        $this->assertEquals('rotating', $oldKey->status);

        // 新密钥 key_id 与旧密钥不同
        $newKeyId = $response->json('data.key_id');
        $this->assertNotEquals($oldKey->key_id, $newKeyId);
    }

    /** 测试已吊销的密钥无法进行轮换，返回 422 */
    public function test_cannot_rotate_revoked_key(): void
    {
        $key = $this->createFakeApiKey(['status' => 'revoked', 'revoked_at' => now()]);

        $response = $this->actingAs($this->owner, 'merchant')
            ->postJson("/api/v1/merchant/api-keys/{$key->key_id}/rotate");

        $response->assertStatus(422);
    }

    /* ----------------------------------------------------------------
     |  密钥吊销
     | ---------------------------------------------------------------- */

    /** 测试吊销密钥后状态变为 revoked */
    public function test_revoke_key_sets_status_to_revoked(): void
    {
        $key = $this->createFakeApiKey();

        $response = $this->actingAs($this->owner, 'merchant')
            ->deleteJson("/api/v1/merchant/api-keys/{$key->key_id}", [
                'reason' => '手动吊销',
            ]);

        $response->assertStatus(200)
            ->assertJsonFragment(['status' => 'revoked']);

        $this->assertDatabaseHas('merchant_api_keys', [
            'id'     => $key->id,
            'status' => 'revoked',
        ]);
    }

    /** 测试吊销已吊销的密钥返回 422 */
    public function test_cannot_revoke_already_revoked_key(): void
    {
        $key = $this->createFakeApiKey([
            'status'     => 'revoked',
            'revoked_at' => now(),
        ]);

        $response = $this->actingAs($this->owner, 'merchant')
            ->deleteJson("/api/v1/merchant/api-keys/{$key->key_id}");

        $response->assertStatus(422);
    }
}
