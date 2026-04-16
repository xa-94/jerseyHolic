<?php

namespace Tests\Feature\Merchant;

use App\Models\Central\Admin;
use App\Models\Central\Domain;
use App\Models\Central\Merchant;
use App\Models\Central\Store;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TenancyTestCase;

/**
 * 站点管理集成测试
 *
 * 覆盖站点的创建、列表、详情、配置更新、域名管理、状态变更等功能。
 */
class StoreManagementTest extends TenancyTestCase
{
    use RefreshDatabase;

    private Admin $admin;
    private Merchant $merchant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = Admin::create([
            'username' => 'store_admin',
            'email'    => 'store_admin@test.com',
            'password' => bcrypt('password'),
            'name'     => 'Store Admin',
            'status'   => 1,
            'is_super' => 1,
        ]);

        $this->merchant = $this->createMerchant(['status' => 1, 'level' => 'standard']);
    }

    /* ----------------------------------------------------------------
     |  站点创建
     | ---------------------------------------------------------------- */

    /** 测试管理员可为商户创建新站点，返回 201 及站点信息 */
    public function test_admin_can_create_store(): void
    {
        $response = $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/v1/admin/stores', [
                'merchant_id' => $this->merchant->id,
                'store_name'  => 'My New Store',
                'store_code'  => 'mynewstore_' . \Illuminate\Support\Str::random(4),
                'domain'      => 'mynewstore.jerseyholic.test',
            ]);

        $response->assertStatus(201)
            ->assertJsonFragment(['store_name' => 'My New Store']);
    }

    /** 测试未认证访问站点创建接口返回 401 */
    public function test_unauthenticated_cannot_create_store(): void
    {
        $response = $this->postJson('/api/v1/admin/stores', [
            'merchant_id' => $this->merchant->id,
            'store_name'  => 'Unauthorized Store',
            'store_code'  => 'unauth_store',
            'domain'      => 'unauth.jerseyholic.test',
        ]);

        $response->assertStatus(401);
    }

    /* ----------------------------------------------------------------
     |  站点列表
     | ---------------------------------------------------------------- */

    /** 测试管理员可获取所有站点列表（支持分页） */
    public function test_admin_can_list_all_stores(): void
    {
        $this->createStore($this->merchant);
        $this->createStore($this->merchant);

        $response = $this->actingAs($this->admin, 'sanctum')
            ->getJson('/api/v1/admin/stores');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => ['list', 'total', 'page', 'per_page'],
            ]);
    }

    /** 测试可按 merchant_id 筛选站点列表，只返回该商户的站点 */
    public function test_admin_can_filter_stores_by_merchant_id(): void
    {
        $otherMerchant = $this->createMerchant(['status' => 1]);

        $storeA = $this->createStore($this->merchant);
        $storeB = $this->createStore($otherMerchant);

        $response = $this->actingAs($this->admin, 'sanctum')
            ->getJson("/api/v1/admin/stores?merchant_id={$this->merchant->id}");

        $response->assertStatus(200);

        $items   = $response->json('data.list');
        $storeIds = array_column($items, 'id');

        $this->assertContains($storeA->id, $storeIds);
        $this->assertNotContains($storeB->id, $storeIds);
    }

    /* ----------------------------------------------------------------
     |  站点详情
     | ---------------------------------------------------------------- */

    /** 测试管理员可获取指定站点详情 */
    public function test_admin_can_get_store_detail(): void
    {
        $store = $this->createStore($this->merchant);

        $response = $this->actingAs($this->admin, 'sanctum')
            ->getJson("/api/v1/admin/stores/{$store->id}");

        $response->assertStatus(200)
            ->assertJsonFragment(['id' => $store->id]);
    }

    /** 测试获取不存在的站点返回 404 */
    public function test_get_nonexistent_store_returns_404(): void
    {
        $response = $this->actingAs($this->admin, 'sanctum')
            ->getJson('/api/v1/admin/stores/99999999');

        $response->assertStatus(404);
    }

    /* ----------------------------------------------------------------
     |  站点配置更新
     | ---------------------------------------------------------------- */

    /** 测试管理员可更新站点产品分类配置 */
    public function test_admin_can_update_store_categories(): void
    {
        $store = $this->createStore($this->merchant);

        $response = $this->actingAs($this->admin, 'sanctum')
            ->patchJson("/api/v1/admin/stores/{$store->id}/categories", [
                'categories' => ['jerseys', 'kits', 'accessories'],
            ]);

        $response->assertStatus(200);

        $this->assertDatabaseHas('jh_stores', [
            'id' => $store->id,
        ]);
    }

    /** 测试管理员可更新站点目标市场配置 */
    public function test_admin_can_update_store_markets(): void
    {
        $store = $this->createStore($this->merchant);

        $response = $this->actingAs($this->admin, 'sanctum')
            ->patchJson("/api/v1/admin/stores/{$store->id}/markets", [
                'markets' => ['US', 'UK', 'DE'],
            ]);

        $response->assertStatus(200);
    }

    /** 测试管理员可更新站点支持语言配置 */
    public function test_admin_can_update_store_languages(): void
    {
        $store = $this->createStore($this->merchant);

        $response = $this->actingAs($this->admin, 'sanctum')
            ->patchJson("/api/v1/admin/stores/{$store->id}/languages", [
                'languages' => ['en', 'zh', 'es'],
            ]);

        $response->assertStatus(200);
    }

    /** 测试管理员可更新站点支持货币配置 */
    public function test_admin_can_update_store_currencies(): void
    {
        $store = $this->createStore($this->merchant);

        $response = $this->actingAs($this->admin, 'sanctum')
            ->patchJson("/api/v1/admin/stores/{$store->id}/currencies", [
                'currencies' => ['USD', 'EUR', 'GBP'],
            ]);

        $response->assertStatus(200);
    }

    /** 测试管理员可更新站点物流配置 */
    public function test_admin_can_update_store_logistics(): void
    {
        $store = $this->createStore($this->merchant);

        $response = $this->actingAs($this->admin, 'sanctum')
            ->patchJson("/api/v1/admin/stores/{$store->id}/logistics", [
                'logistics' => [
                    'providers' => ['dhl', 'fedex'],
                    'free_shipping_threshold' => 100,
                ],
            ]);

        $response->assertStatus(200);
    }

    /** 测试管理员可通过 PUT 更新站点基本信息 */
    public function test_admin_can_update_store_basic_info(): void
    {
        $store = $this->createStore($this->merchant);

        $response = $this->actingAs($this->admin, 'sanctum')
            ->putJson("/api/v1/admin/stores/{$store->id}", [
                'store_name' => 'Updated Store Name',
            ]);

        $response->assertStatus(200)
            ->assertJsonFragment(['store_name' => 'Updated Store Name']);
    }

    /* ----------------------------------------------------------------
     |  域名管理
     | ---------------------------------------------------------------- */

    /** 测试管理员可为站点添加新域名 */
    public function test_admin_can_add_domain_to_store(): void
    {
        $store = $this->createStore($this->merchant);

        $response = $this->actingAs($this->admin, 'sanctum')
            ->postJson("/api/v1/admin/stores/{$store->id}/domains", [
                'domain' => 'www.newdomain-' . \Illuminate\Support\Str::random(4) . '.test',
            ]);

        $response->assertStatus(201)
            ->assertJsonStructure(['data' => ['domain']]);
    }

    /** 测试管理员可移除站点已有域名 */
    public function test_admin_can_remove_domain_from_store(): void
    {
        $store  = $this->createStore($this->merchant);
        $domain = $this->createDomain($store, 'removeme-' . \Illuminate\Support\Str::random(4) . '.test');

        $response = $this->actingAs($this->admin, 'sanctum')
            ->deleteJson("/api/v1/admin/stores/{$store->id}/domains/{$domain->id}");

        $response->assertStatus(200);

        $this->assertDatabaseMissing('jh_domains', ['id' => $domain->id]);
    }

    /** 测试移除不存在的域名返回 404 */
    public function test_remove_nonexistent_domain_returns_404(): void
    {
        $store = $this->createStore($this->merchant);

        $response = $this->actingAs($this->admin, 'sanctum')
            ->deleteJson("/api/v1/admin/stores/{$store->id}/domains/99999999");

        $response->assertStatus(404);
    }

    /* ----------------------------------------------------------------
     |  站点状态变更
     | ---------------------------------------------------------------- */

    /** 测试管理员可将站点状态变更为 maintenance */
    public function test_admin_can_set_store_status_to_maintenance(): void
    {
        $store = $this->createStore($this->merchant, ['status' => 1]);

        $response = $this->actingAs($this->admin, 'sanctum')
            ->patchJson("/api/v1/admin/stores/{$store->id}/status", [
                'status' => 'maintenance',
            ]);

        $response->assertStatus(200);

        $this->assertDatabaseHas('jh_stores', [
            'id'     => $store->id,
            'status' => 2, // maintenance
        ]);
    }

    /** 测试管理员可将站点状态变更为 inactive */
    public function test_admin_can_set_store_status_to_inactive(): void
    {
        $store = $this->createStore($this->merchant, ['status' => 1]);

        $response = $this->actingAs($this->admin, 'sanctum')
            ->patchJson("/api/v1/admin/stores/{$store->id}/status", [
                'status' => 'inactive',
            ]);

        $response->assertStatus(200);

        $this->assertDatabaseHas('jh_stores', [
            'id'     => $store->id,
            'status' => 0, // inactive
        ]);
    }

    /** 测试管理员可将站点从 inactive 恢复为 active */
    public function test_admin_can_restore_store_to_active(): void
    {
        $store = $this->createStore($this->merchant, ['status' => 0]);

        $response = $this->actingAs($this->admin, 'sanctum')
            ->patchJson("/api/v1/admin/stores/{$store->id}/status", [
                'status' => 'active',
            ]);

        $response->assertStatus(200);

        $this->assertDatabaseHas('jh_stores', [
            'id'     => $store->id,
            'status' => 1, // active
        ]);
    }

    /** 测试非法状态值返回 422 */
    public function test_set_store_status_with_invalid_value_returns_422(): void
    {
        $store = $this->createStore($this->merchant);

        $response = $this->actingAs($this->admin, 'sanctum')
            ->patchJson("/api/v1/admin/stores/{$store->id}/status", [
                'status' => 'unknown_status',
            ]);

        $response->assertStatus(422);
    }
}
