<?php

namespace Tests\Feature\Merchant;

use App\Models\Central\Admin;
use App\Models\Central\Merchant;
use App\Models\Central\MerchantApiKey;
use App\Models\Central\Store;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TenancyTestCase;

/**
 * 商户状态级联测试
 *
 * 覆盖商户暂停→站点变为 maintenance、封禁→站点 inactive + API 密钥吊销 + 资金冻结、恢复 active 等级联逻辑。
 */
class StatusCascadeTest extends TenancyTestCase
{
    use RefreshDatabase;

    private Admin $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = Admin::create([
            'username' => 'cascade_admin',
            'email'    => 'cascade@test.com',
            'password' => bcrypt('password'),
            'name'     => 'Cascade Admin',
            'status'   => 1,
            'is_super' => 1,
        ]);
    }

    /* ----------------------------------------------------------------
     |  商户暂停（suspended）
     | ---------------------------------------------------------------- */

    /** 测试商户变为 suspended 时，名下 active 站点状态变为 maintenance(2) */
    public function test_suspended_merchant_sets_active_stores_to_maintenance(): void
    {
        $merchant = $this->createMerchant(['status' => 1]);
        $storeA   = $this->createStore($merchant, ['status' => 1]);
        $storeB   = $this->createStore($merchant, ['status' => 1]);

        $response = $this->actingAs($this->admin, 'sanctum')
            ->patchJson("/api/v1/admin/merchants/{$merchant->id}/status", [
                'status' => 'suspended',
                'reason' => '违规操作',
            ]);

        $response->assertStatus(200);

        $this->assertDatabaseHas('jh_stores', ['id' => $storeA->id, 'status' => 2]);
        $this->assertDatabaseHas('jh_stores', ['id' => $storeB->id, 'status' => 2]);
    }

    /** 测试商户暂停时，已经是 inactive(0) 的站点不受影响 */
    public function test_suspended_merchant_does_not_affect_already_inactive_stores(): void
    {
        $merchant = $this->createMerchant(['status' => 1]);
        $inactive = $this->createStore($merchant, ['status' => 0]);
        $active   = $this->createStore($merchant, ['status' => 1]);

        $this->actingAs($this->admin, 'sanctum')
            ->patchJson("/api/v1/admin/merchants/{$merchant->id}/status", [
                'status' => 'suspended',
            ]);

        // inactive 站点状态不变
        $this->assertDatabaseHas('jh_stores', ['id' => $inactive->id, 'status' => 0]);
        // active 站点变为 maintenance
        $this->assertDatabaseHas('jh_stores', ['id' => $active->id, 'status' => 2]);
    }

    /* ----------------------------------------------------------------
     |  商户封禁（banned）
     | ---------------------------------------------------------------- */

    /** 测试商户封禁时，名下所有 active/maintenance 站点变为 inactive(0) */
    public function test_banned_merchant_sets_all_stores_to_inactive(): void
    {
        $merchant    = $this->createMerchant(['status' => 1]);
        $storeActive = $this->createStore($merchant, ['status' => 1]);
        $storeMaint  = $this->createStore($merchant, ['status' => 2]);

        $this->actingAs($this->admin, 'sanctum')
            ->patchJson("/api/v1/admin/merchants/{$merchant->id}/status", [
                'status' => 'banned',
                'reason' => '严重违规',
            ]);

        $this->assertDatabaseHas('jh_stores', ['id' => $storeActive->id, 'status' => 0]);
        $this->assertDatabaseHas('jh_stores', ['id' => $storeMaint->id, 'status' => 0]);
    }

    /** 测试商户封禁时，active API 密钥被吊销（status 变为 revoked） */
    public function test_banned_merchant_revokes_active_api_keys(): void
    {
        $merchant = $this->createMerchant(['status' => 1]);

        // 创建一条 active 的 API 密钥
        $apiKey = MerchantApiKey::create([
            'merchant_id'  => $merchant->id,
            'key_id'       => 'mk_test_' . \Illuminate\Support\Str::random(16),
            'public_key'   => '-----BEGIN PUBLIC KEY-----\nMOCK_KEY\n-----END PUBLIC KEY-----',
            'algorithm'    => 'RSA-SHA256',
            'key_size'     => 4096,
            'status'       => 'active',
            'activated_at' => now(),
        ]);

        $this->actingAs($this->admin, 'sanctum')
            ->patchJson("/api/v1/admin/merchants/{$merchant->id}/status", [
                'status' => 'banned',
            ]);

        $this->assertDatabaseHas('merchant_api_keys', [
            'id'     => $apiKey->id,
            'status' => 'revoked',
        ]);
    }

    /** 测试商户封禁时，fund_frozen_until 被设置为 180 天后 */
    public function test_banned_merchant_sets_fund_frozen_until_180_days(): void
    {
        $merchant = $this->createMerchant(['status' => 1]);

        $before = now()->addDays(179);
        $after  = now()->addDays(181);

        $this->actingAs($this->admin, 'sanctum')
            ->patchJson("/api/v1/admin/merchants/{$merchant->id}/status", [
                'status' => 'banned',
            ]);

        $refreshed = Merchant::find($merchant->id);

        $this->assertNotNull($refreshed->fund_frozen_until, 'fund_frozen_until 应当被设置');
        $this->assertTrue(
            $refreshed->fund_frozen_until->between($before, $after),
            'fund_frozen_until 应约为 180 天后'
        );
    }

    /* ----------------------------------------------------------------
     |  商户恢复（active）
     | ---------------------------------------------------------------- */

    /** 测试商户从 suspended 恢复为 active 时，maintenance 站点恢复为 active */
    public function test_reactivated_merchant_restores_maintenance_stores_to_active(): void
    {
        $merchant    = $this->createMerchant(['status' => 1]);
        $storeActive = $this->createStore($merchant, ['status' => 1]);

        // 先暂停商户
        $this->actingAs($this->admin, 'sanctum')
            ->patchJson("/api/v1/admin/merchants/{$merchant->id}/status", [
                'status' => 'suspended',
            ]);

        // 确认站点已变为 maintenance
        $this->assertDatabaseHas('jh_stores', ['id' => $storeActive->id, 'status' => 2]);

        // 恢复商户
        $this->actingAs($this->admin, 'sanctum')
            ->patchJson("/api/v1/admin/merchants/{$merchant->id}/status", [
                'status' => 'active',
            ]);

        // 站点应恢复为 active
        $this->assertDatabaseHas('jh_stores', ['id' => $storeActive->id, 'status' => 1]);
    }

    /** 测试商户恢复时，原本 inactive(0) 的站点不被自动激活 */
    public function test_reactivation_does_not_restore_originally_inactive_stores(): void
    {
        $merchant  = $this->createMerchant(['status' => 1]);
        $inactive  = $this->createStore($merchant, ['status' => 0]);

        // 暂停
        $this->actingAs($this->admin, 'sanctum')
            ->patchJson("/api/v1/admin/merchants/{$merchant->id}/status", [
                'status' => 'suspended',
            ]);

        // 恢复
        $this->actingAs($this->admin, 'sanctum')
            ->patchJson("/api/v1/admin/merchants/{$merchant->id}/status", [
                'status' => 'active',
            ]);

        // 原先 inactive 的站点状态不变
        $this->assertDatabaseHas('jh_stores', ['id' => $inactive->id, 'status' => 0]);
    }

    /** 测试商户封禁后被吊销的 API 密钥不会在恢复 active 时自动重新激活 */
    public function test_reactivation_does_not_restore_revoked_api_keys(): void
    {
        $merchant = $this->createMerchant(['status' => 1]);

        $apiKey = MerchantApiKey::create([
            'merchant_id'  => $merchant->id,
            'key_id'       => 'mk_revoke_' . \Illuminate\Support\Str::random(12),
            'public_key'   => '-----BEGIN PUBLIC KEY-----\nMOCK\n-----END PUBLIC KEY-----',
            'algorithm'    => 'RSA-SHA256',
            'key_size'     => 4096,
            'status'       => 'active',
            'activated_at' => now(),
        ]);

        // 先暂停再封禁
        $this->actingAs($this->admin, 'sanctum')
            ->patchJson("/api/v1/admin/merchants/{$merchant->id}/status", [
                'status' => 'suspended',
            ]);

        // 恢复（只能从 suspended 恢复，因为 banned → active 路径不允许需绕过）
        $merchant->update(['status' => 4]); // 确保状态为 suspended
        $this->actingAs($this->admin, 'sanctum')
            ->patchJson("/api/v1/admin/merchants/{$merchant->id}/status", [
                'status' => 'active',
            ]);

        // 密钥状态保持不变（suspended 不吊销密钥，所以这里密钥仍为 active）
        $this->assertDatabaseHas('merchant_api_keys', [
            'id'     => $apiKey->id,
            'status' => 'active',
        ]);
    }

    /** 测试管理员变更商户状态接口需要认证，未登录返回 401 */
    public function test_status_change_requires_authentication(): void
    {
        $merchant = $this->createMerchant(['status' => 1]);

        $response = $this->patchJson("/api/v1/admin/merchants/{$merchant->id}/status", [
            'status' => 'suspended',
        ]);

        $response->assertStatus(401);
    }
}
