<?php

namespace Tests\Feature\Merchant;

use App\Models\Central\Admin;
use App\Models\Central\Merchant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TenancyTestCase;

/**
 * 商户 CRUD 集成测试
 *
 * 覆盖商户的注册、管理员创建、列表、详情、更新、状态变更和等级变更接口。
 */
class MerchantCrudTest extends TenancyTestCase
{
    use RefreshDatabase;

    /** 测试用管理员实例 */
    private Admin $admin;

    protected function setUp(): void
    {
        parent::setUp();

        // 创建超管，用于需要认证的管理端接口
        $this->admin = Admin::create([
            'username'   => 'superadmin',
            'email'      => 'admin@test.com',
            'password'   => bcrypt('password'),
            'name'       => 'Super Admin',
            'status'     => 1,
            'is_super'   => 1,
        ]);
    }

    /* ----------------------------------------------------------------
     |  商户注册（公开接口）
     | ---------------------------------------------------------------- */

    /** 测试商户通过公开接口注册成功，返回 201 及商户信息 */
    public function test_merchant_can_register_via_public_endpoint(): void
    {
        $response = $this->postJson('/api/v1/merchant/register', [
            'merchant_name' => 'Test Shop',
            'email'         => 'shop@example.com',
            'password'      => 'Secret123!',
            'contact_name'  => 'John Doe',
            'phone'         => '+8613800138000',
        ]);

        $response->assertStatus(201)
            ->assertJsonFragment(['email' => 'shop@example.com']);

        $this->assertDatabaseHas('jh_merchants', [
            'email'  => 'shop@example.com',
            'status' => 0, // pending
        ]);
    }

    /** 测试使用重复邮箱注册返回 422 验证错误 */
    public function test_register_with_duplicate_email_returns_422(): void
    {
        $this->createMerchant(['email' => 'dup@example.com']);

        $response = $this->postJson('/api/v1/merchant/register', [
            'merchant_name' => 'Another Shop',
            'email'         => 'dup@example.com',
            'password'      => 'Secret123!',
            'contact_name'  => 'Jane Doe',
        ]);

        $response->assertStatus(422);
    }

    /** 测试缺少必填字段注册返回 422 */
    public function test_register_missing_required_fields_returns_422(): void
    {
        $response = $this->postJson('/api/v1/merchant/register', [
            'email' => 'incomplete@example.com',
        ]);

        $response->assertStatus(422);
    }

    /* ----------------------------------------------------------------
     |  管理员创建商户
     | ---------------------------------------------------------------- */

    /** 测试管理员可通过后台接口创建商户，初始状态为 pending */
    public function test_admin_can_create_merchant(): void
    {
        $response = $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/v1/admin/merchants', [
                'merchant_name' => 'Admin Created Shop',
                'email'         => 'admin_created@example.com',
                'password'      => 'Secret123!',
                'contact_name'  => 'Contact Person',
                'phone'         => '+8613900000001',
                'level'         => 'standard',
            ]);

        $response->assertStatus(200)
            ->assertJsonFragment(['email' => 'admin_created@example.com']);

        $this->assertDatabaseHas('jh_merchants', [
            'email'  => 'admin_created@example.com',
            'status' => 0, // pending
            'level'  => 'standard',
        ]);
    }

    /** 测试未认证访问管理端商户创建接口返回 401 */
    public function test_unauthenticated_cannot_create_merchant_via_admin(): void
    {
        $response = $this->postJson('/api/v1/admin/merchants', [
            'merchant_name' => 'Unauth Shop',
            'email'         => 'unauth@example.com',
            'password'      => 'Secret123!',
            'contact_name'  => 'No Auth',
        ]);

        $response->assertStatus(401);
    }

    /* ----------------------------------------------------------------
     |  商户列表
     | ---------------------------------------------------------------- */

    /** 测试管理员可获取商户列表，结果包含分页数据 */
    public function test_admin_can_list_merchants_with_pagination(): void
    {
        $this->createMerchant(['merchant_name' => 'Merchant A', 'status' => 1]);
        $this->createMerchant(['merchant_name' => 'Merchant B', 'status' => 0]);

        $response = $this->actingAs($this->admin, 'sanctum')
            ->getJson('/api/v1/admin/merchants?per_page=10');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'code',
                'data' => ['list', 'total', 'page', 'per_page'],
            ]);

        $data = $response->json('data');
        $this->assertGreaterThanOrEqual(2, $data['total']);
    }

    /** 测试可按状态筛选商户列表 */
    public function test_admin_can_filter_merchants_by_status(): void
    {
        $this->createMerchant(['email' => 'active1@test.com', 'status' => 1]);
        $this->createMerchant(['email' => 'pending1@test.com', 'status' => 0]);

        $response = $this->actingAs($this->admin, 'sanctum')
            ->getJson('/api/v1/admin/merchants?status=active');

        $response->assertStatus(200);

        $items = $response->json('data.list');
        foreach ($items as $item) {
            $this->assertEquals(1, $item['status']);
        }
    }

    /** 测试可按关键词搜索商户列表 */
    public function test_admin_can_filter_merchants_by_keyword(): void
    {
        $this->createMerchant(['merchant_name' => 'UniqueKeywordShop', 'email' => 'kw@test.com']);
        $this->createMerchant(['merchant_name' => 'OtherShop', 'email' => 'other@test.com']);

        $response = $this->actingAs($this->admin, 'sanctum')
            ->getJson('/api/v1/admin/merchants?keyword=UniqueKeyword');

        $response->assertStatus(200);

        $items = $response->json('data.list');
        $this->assertCount(1, $items);
        $this->assertStringContainsString('UniqueKeyword', $items[0]['merchant_name']);
    }

    /** 测试可按等级筛选商户列表 */
    public function test_admin_can_filter_merchants_by_level(): void
    {
        $this->createMerchant(['level' => 'vip', 'email' => 'vip1@test.com']);
        $this->createMerchant(['level' => 'starter', 'email' => 'starter1@test.com']);

        $response = $this->actingAs($this->admin, 'sanctum')
            ->getJson('/api/v1/admin/merchants?level=vip');

        $response->assertStatus(200);

        $items = $response->json('data.list');
        foreach ($items as $item) {
            $this->assertEquals('vip', $item['level']);
        }
    }

    /* ----------------------------------------------------------------
     |  商户详情
     | ---------------------------------------------------------------- */

    /** 测试管理员可获取指定商户详情 */
    public function test_admin_can_get_merchant_detail(): void
    {
        $merchant = $this->createMerchant(['merchant_name' => 'Detail Shop']);

        $response = $this->actingAs($this->admin, 'sanctum')
            ->getJson("/api/v1/admin/merchants/{$merchant->id}");

        $response->assertStatus(200)
            ->assertJsonFragment(['id' => $merchant->id])
            ->assertJsonFragment(['merchant_name' => 'Detail Shop']);
    }

    /** 测试获取不存在的商户返回 404 */
    public function test_get_nonexistent_merchant_returns_404(): void
    {
        $response = $this->actingAs($this->admin, 'sanctum')
            ->getJson('/api/v1/admin/merchants/99999999');

        $response->assertStatus(404);
    }

    /* ----------------------------------------------------------------
     |  商户更新
     | ---------------------------------------------------------------- */

    /** 测试管理员可更新商户基本信息 */
    public function test_admin_can_update_merchant_info(): void
    {
        $merchant = $this->createMerchant();

        $response = $this->actingAs($this->admin, 'sanctum')
            ->putJson("/api/v1/admin/merchants/{$merchant->id}", [
                'merchant_name' => 'Updated Shop Name',
                'contact_name'  => 'New Contact',
            ]);

        $response->assertStatus(200)
            ->assertJsonFragment(['merchant_name' => 'Updated Shop Name']);

        $this->assertDatabaseHas('jh_merchants', [
            'id'            => $merchant->id,
            'merchant_name' => 'Updated Shop Name',
            'contact_name'  => 'New Contact',
        ]);
    }

    /** 测试更新商户邮箱时检查唯一性，重复则返回 422 */
    public function test_update_merchant_with_duplicate_email_returns_422(): void
    {
        $merchant1 = $this->createMerchant(['email' => 'unique_m1@test.com']);
        $merchant2 = $this->createMerchant(['email' => 'unique_m2@test.com']);

        $response = $this->actingAs($this->admin, 'sanctum')
            ->putJson("/api/v1/admin/merchants/{$merchant2->id}", [
                'email' => 'unique_m1@test.com', // 已被 merchant1 使用
            ]);

        $response->assertStatus(422);
    }

    /* ----------------------------------------------------------------
     |  商户状态变更
     | ---------------------------------------------------------------- */

    /** 测试管理员可将 active 商户状态变更为 suspended */
    public function test_admin_can_change_merchant_status_to_suspended(): void
    {
        $merchant = $this->createMerchant(['status' => 1]);

        $response = $this->actingAs($this->admin, 'sanctum')
            ->patchJson("/api/v1/admin/merchants/{$merchant->id}/status", [
                'status' => 'suspended',
                'reason' => '违规操作',
            ]);

        $response->assertStatus(200);

        $this->assertDatabaseHas('jh_merchants', [
            'id'     => $merchant->id,
            'status' => 4, // suspended
        ]);
    }

    /** 测试非法状态值变更返回 422 */
    public function test_change_merchant_status_with_invalid_value_returns_422(): void
    {
        $merchant = $this->createMerchant(['status' => 1]);

        $response = $this->actingAs($this->admin, 'sanctum')
            ->patchJson("/api/v1/admin/merchants/{$merchant->id}/status", [
                'status' => 'unknown_status',
            ]);

        $response->assertStatus(422);
    }

    /** 测试不允许的状态变更路径返回 422（如 active 直接跳到 rejected） */
    public function test_invalid_status_transition_returns_422(): void
    {
        $merchant = $this->createMerchant(['status' => 1]); // active

        $response = $this->actingAs($this->admin, 'sanctum')
            ->patchJson("/api/v1/admin/merchants/{$merchant->id}/status", [
                'status' => 'rejected', // active 不能直接 rejected
            ]);

        $response->assertStatus(422);
    }

    /* ----------------------------------------------------------------
     |  商户等级变更
     | ---------------------------------------------------------------- */

    /** 测试管理员可将商户等级升级为 vip */
    public function test_admin_can_update_merchant_level(): void
    {
        $merchant = $this->createMerchant(['level' => 'starter']);

        $response = $this->actingAs($this->admin, 'sanctum')
            ->patchJson("/api/v1/admin/merchants/{$merchant->id}/level", [
                'level' => 'vip',
            ]);

        $response->assertStatus(200)
            ->assertJsonFragment(['level' => 'vip']);

        $this->assertDatabaseHas('jh_merchants', [
            'id'    => $merchant->id,
            'level' => 'vip',
        ]);
    }

    /** 测试非法等级值返回 422 */
    public function test_update_merchant_level_with_invalid_value_returns_422(): void
    {
        $merchant = $this->createMerchant();

        $response = $this->actingAs($this->admin, 'sanctum')
            ->patchJson("/api/v1/admin/merchants/{$merchant->id}/level", [
                'level' => 'diamond', // 不存在的等级
            ]);

        $response->assertStatus(422);
    }

    /** 测试升级为 vip 时响应中站点上限标记为"无限制" */
    public function test_vip_level_returns_unlimited_store_limit(): void
    {
        $merchant = $this->createMerchant(['level' => 'starter']);

        $response = $this->actingAs($this->admin, 'sanctum')
            ->patchJson("/api/v1/admin/merchants/{$merchant->id}/level", [
                'level' => 'vip',
            ]);

        $response->assertStatus(200)
            ->assertJsonFragment(['store_limit' => '无限制']);
    }
}
