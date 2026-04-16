<?php

namespace Tests\Feature\Merchant;

use App\Models\Central\Merchant;
use App\Models\Central\MerchantUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TenancyTestCase;

/**
 * 商户用户管理集成测试
 *
 * 覆盖子账号创建、角色权限控制、owner 保护、allowed_store_ids 验证、密码修改及账户解锁。
 */
class MerchantUserTest extends TenancyTestCase
{
    use RefreshDatabase;

    private Merchant $merchant;
    private MerchantUser $owner;

    protected function setUp(): void
    {
        parent::setUp();

        $this->merchant = $this->createMerchant(['status' => 1]);

        $this->owner = MerchantUser::create([
            'merchant_id'    => $this->merchant->id,
            'username'       => 'shop_owner',
            'email'          => 'owner@testshop.com',
            'password'       => Hash::make('Password123!'),
            'name'           => 'Shop Owner',
            'role'           => 'owner',
            'status'         => 1,
            'login_failures' => 0,
        ]);
    }

    /** 快速创建同商户下其他角色用户 */
    private function makeUser(string $role = 'operator', array $extra = []): MerchantUser
    {
        return MerchantUser::create(array_merge([
            'merchant_id'    => $this->merchant->id,
            'username'       => 'user_' . Str::random(6),
            'email'          => 'u_' . Str::random(8) . '@test.com',
            'password'       => Hash::make('Password123!'),
            'name'           => ucfirst($role) . ' User',
            'role'           => $role,
            'status'         => 1,
            'login_failures' => 0,
        ], $extra));
    }

    /* ----------------------------------------------------------------
     |  创建子账号
     | ---------------------------------------------------------------- */

    /** 测试 owner 可创建新子账号 */
    public function test_owner_can_create_sub_account(): void
    {
        $response = $this->actingAs($this->owner, 'merchant')
            ->postJson('/api/v1/merchant/users', [
                'username' => 'new_operator',
                'email'    => 'new_op@test.com',
                'password' => 'Password123!',
                'name'     => 'New Operator',
                'role'     => 'operator',
            ]);

        $response->assertStatus(201)
            ->assertJsonFragment(['username' => 'new_operator'])
            ->assertJsonFragment(['role' => 'operator']);

        $this->assertDatabaseHas('jh_merchant_users', [
            'username'    => 'new_operator',
            'merchant_id' => $this->merchant->id,
        ]);
    }

    /** 测试 manager 只能创建 operator 角色，不能创建 manager 或 owner */
    public function test_manager_can_only_create_operator(): void
    {
        $manager = $this->makeUser('manager');

        // 尝试创建 manager 角色 → 拒绝
        $responseFail = $this->actingAs($manager, 'merchant')
            ->postJson('/api/v1/merchant/users', [
                'username' => 'new_manager',
                'email'    => 'new_manager@test.com',
                'password' => 'Password123!',
                'name'     => 'New Manager',
                'role'     => 'manager',
            ]);
        $responseFail->assertStatus(403);

        // 创建 operator 角色 → 成功
        $responseOk = $this->actingAs($manager, 'merchant')
            ->postJson('/api/v1/merchant/users', [
                'username' => 'new_op_by_mgr',
                'email'    => 'new_op_by_mgr@test.com',
                'password' => 'Password123!',
                'name'     => 'New Operator',
                'role'     => 'operator',
            ]);
        $responseOk->assertStatus(201);
    }

    /** 测试 operator 无权创建子账号，返回 403 */
    public function test_operator_cannot_create_sub_account(): void
    {
        $operator = $this->makeUser('operator');

        $response = $this->actingAs($operator, 'merchant')
            ->postJson('/api/v1/merchant/users', [
                'username' => 'sneaky_user',
                'email'    => 'sneaky@test.com',
                'password' => 'Password123!',
                'name'     => 'Sneaky',
                'role'     => 'operator',
            ]);

        $response->assertStatus(403);
    }

    /** 测试创建子账号时邮箱重复返回 422 */
    public function test_create_user_with_duplicate_email_returns_422(): void
    {
        $this->makeUser('operator', ['email' => 'dup_email@test.com']);

        $response = $this->actingAs($this->owner, 'merchant')
            ->postJson('/api/v1/merchant/users', [
                'username' => 'another_op',
                'email'    => 'dup_email@test.com',
                'password' => 'Password123!',
                'name'     => 'Another Op',
                'role'     => 'operator',
            ]);

        $response->assertStatus(422);
    }

    /* ----------------------------------------------------------------
     |  删除用户（owner 保护）
     | ---------------------------------------------------------------- */

    /** 测试 owner 账户不可被删除，返回 422 */
    public function test_owner_cannot_be_deleted(): void
    {
        $response = $this->actingAs($this->owner, 'merchant')
            ->deleteJson("/api/v1/merchant/users/{$this->owner->id}");

        $response->assertStatus(422);
    }

    /** 测试 manager 只能删除 operator，不能删除 manager 或 owner */
    public function test_manager_can_only_delete_operator(): void
    {
        $manager = $this->makeUser('manager');
        $op      = $this->makeUser('operator');

        // 尝试删除 manager 自身 → 被拒绝（manager 只能删 operator）
        $failResponse = $this->actingAs($manager, 'merchant')
            ->deleteJson("/api/v1/merchant/users/{$manager->id}");
        // manager 不能删非 operator，应返回 403 或 422
        $this->assertContains($failResponse->getStatusCode(), [403, 422]);

        // 删除 operator → 成功
        $okResponse = $this->actingAs($manager, 'merchant')
            ->deleteJson("/api/v1/merchant/users/{$op->id}");
        $okResponse->assertStatus(200);
    }

    /** 测试 operator 无权删除用户，返回 403 */
    public function test_operator_cannot_delete_users(): void
    {
        $operator = $this->makeUser('operator');
        $target   = $this->makeUser('operator');

        $response = $this->actingAs($operator, 'merchant')
            ->deleteJson("/api/v1/merchant/users/{$target->id}");

        $response->assertStatus(403);
    }

    /* ----------------------------------------------------------------
     |  allowed_store_ids 更新
     | ---------------------------------------------------------------- */

    /** 测试 owner 可更新子账号的 allowed_store_ids */
    public function test_owner_can_update_user_allowed_store_ids(): void
    {
        $store = $this->createStore($this->merchant);
        $op    = $this->makeUser('operator');

        $response = $this->actingAs($this->owner, 'merchant')
            ->patchJson("/api/v1/merchant/users/{$op->id}/permissions", [
                'allowed_store_ids' => [$store->id],
            ]);

        $response->assertStatus(200)
            ->assertJsonFragment(['allowed_store_ids' => [$store->id]]);

        $op->refresh();
        $this->assertEquals([$store->id], $op->allowed_store_ids);
    }

    /** 测试 allowed_store_ids 只能包含本商户的站点，传入他商户站点返回 422 */
    public function test_allowed_store_ids_must_belong_to_same_merchant(): void
    {
        $otherMerchant = $this->createMerchant(['status' => 1]);
        $otherStore    = $this->createStore($otherMerchant);
        $op            = $this->makeUser('operator');

        $response = $this->actingAs($this->owner, 'merchant')
            ->patchJson("/api/v1/merchant/users/{$op->id}/permissions", [
                'allowed_store_ids' => [$otherStore->id], // 属于他商户
            ]);

        $response->assertStatus(422);
    }

    /** 测试将 allowed_store_ids 设为 null 表示开放全站点访问 */
    public function test_setting_allowed_store_ids_to_null_grants_all_store_access(): void
    {
        $op = $this->makeUser('operator', ['allowed_store_ids' => [999]]);

        $response = $this->actingAs($this->owner, 'merchant')
            ->patchJson("/api/v1/merchant/users/{$op->id}/permissions", [
                'allowed_store_ids' => null,
            ]);

        $response->assertStatus(200);

        $op->refresh();
        $this->assertNull($op->allowed_store_ids);
    }

    /* ----------------------------------------------------------------
     |  密码修改
     | ---------------------------------------------------------------- */

    /** 测试 owner 可修改子账号密码 */
    public function test_owner_can_change_sub_account_password(): void
    {
        $op = $this->makeUser('operator');

        $response = $this->actingAs($this->owner, 'merchant')
            ->patchJson("/api/v1/merchant/users/{$op->id}/password", [
                'password' => 'NewPassword456!',
            ]);

        $response->assertStatus(200);

        // 验证密码已被更新
        $op->refresh();
        $this->assertTrue(Hash::check('NewPassword456!', $op->password));
    }

    /** 测试密码长度不足 8 位返回 422 */
    public function test_change_password_with_short_password_returns_422(): void
    {
        $op = $this->makeUser('operator');

        $response = $this->actingAs($this->owner, 'merchant')
            ->patchJson("/api/v1/merchant/users/{$op->id}/password", [
                'password' => '123',
            ]);

        $response->assertStatus(422);
    }

    /* ----------------------------------------------------------------
     |  账户解锁
     | ---------------------------------------------------------------- */

    /** 测试 owner 可解锁被锁定的子账号 */
    public function test_owner_can_unlock_locked_user(): void
    {
        $locked = $this->makeUser('operator', [
            'login_failures' => 5,
            'locked_until'   => now()->addMinutes(15),
        ]);

        $response = $this->actingAs($this->owner, 'merchant')
            ->postJson("/api/v1/merchant/users/{$locked->id}/unlock");

        $response->assertStatus(200);

        $locked->refresh();
        $this->assertEquals(0, $locked->login_failures);
        $this->assertNull($locked->locked_until);
    }

    /** 测试 operator 无权解锁用户，返回 403 */
    public function test_operator_cannot_unlock_users(): void
    {
        $operator = $this->makeUser('operator');
        $locked   = $this->makeUser('operator', [
            'login_failures' => 5,
            'locked_until'   => now()->addMinutes(15),
        ]);

        $response = $this->actingAs($operator, 'merchant')
            ->postJson("/api/v1/merchant/users/{$locked->id}/unlock");

        $response->assertStatus(403);
    }

    /* ----------------------------------------------------------------
     |  跨商户访问防护
     | ---------------------------------------------------------------- */

    /** 测试商户 A 的 owner 无法查看商户 B 的用户详情，返回 404 */
    public function test_owner_cannot_access_other_merchant_users(): void
    {
        $otherMerchant = $this->createMerchant(['status' => 1]);
        $otherUser     = MerchantUser::create([
            'merchant_id'    => $otherMerchant->id,
            'username'       => 'other_user',
            'email'          => 'other_user@test.com',
            'password'       => Hash::make('Password123!'),
            'name'           => 'Other User',
            'role'           => 'operator',
            'status'         => 1,
            'login_failures' => 0,
        ]);

        $response = $this->actingAs($this->owner, 'merchant')
            ->getJson("/api/v1/merchant/users/{$otherUser->id}");

        $response->assertStatus(404);
    }
}
