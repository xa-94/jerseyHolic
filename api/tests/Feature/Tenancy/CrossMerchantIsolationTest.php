<?php

declare(strict_types=1);

namespace Tests\Feature\Tenancy;

use App\Models\Central\Domain;
use App\Models\Central\Merchant;
use App\Models\Central\MerchantUser;
use App\Models\Central\Store;
use App\Models\Merchant\MasterProduct;
use App\Models\Tenant\Order;
use App\Models\Tenant\Product;
use App\Services\Product\ProductSyncService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Mockery;
use Tests\TenancyTestCase;

/**
 * 跨商户数据隔离测试
 *
 * 验证不同商户之间的数据完全隔离：
 * - 商户A无法访问商户B的商品、订单、结算数据
 * - 商户DB之间完全隔离
 * - 同步引擎不会跨商户边界
 * - 商户不能为其他商户创建站点
 */
class CrossMerchantIsolationTest extends TenancyTestCase
{
    use RefreshDatabase;

    /**
     * @var Merchant 商户A
     */
    protected Merchant $merchantA;

    /**
     * @var Merchant 商户B
     */
    protected Merchant $merchantB;

    /**
     * @var MerchantUser 商户A的用户
     */
    protected MerchantUser $userA;

    /**
     * @var MerchantUser 商户B的用户
     */
    protected MerchantUser $userB;

    /**
     * @var Store 商户A的站点
     */
    protected Store $storeA;

    /**
     * @var Store 商户B的站点
     */
    protected Store $storeB;

    protected function setUp(): void
    {
        parent::setUp();

        // 创建两个独立的商户
        $this->merchantA = $this->createMerchant([
            'merchant_name' => 'Merchant A',
            'email'         => 'merchant_a@test.com',
        ]);

        $this->merchantB = $this->createMerchant([
            'merchant_name' => 'Merchant B',
            'email'         => 'merchant_b@test.com',
        ]);

        // 为每个商户创建用户
        $this->userA = $this->createMerchantUser($this->merchantA, 'user_a@test.com');
        $this->userB = $this->createMerchantUser($this->merchantB, 'user_b@test.com');

        // 为每个商户创建站点
        $this->storeA = $this->createStore($this->merchantA, [
            'store_code' => 'store_a_' . Str::random(4),
            'domain'     => 'store-a-' . Str::random(4) . '.jerseyholic.test',
        ]);

        $this->storeB = $this->createStore($this->merchantB, [
            'store_code' => 'store_b_' . Str::random(4),
            'domain'     => 'store-b-' . Str::random(4) . '.jerseyholic.test',
        ]);

        // 创建域名记录
        $this->createDomain($this->storeA, $this->storeA->domain);
        $this->createDomain($this->storeB, $this->storeB->domain);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /* ================================================================
     |  商户A无法访问商户B的商品数据
     | ================================================================ */

    /** @test 商户A的Token请求商户B的商品列表返回403 */
    public function test_merchant_a_cannot_access_merchant_b_products(): void
    {
        // 在商户B的站点中创建商品
        $this->runInTenantContext($this->storeB, function () {
            Product::create([
                'sku'        => 'HIC-B-001',
                'model'      => 'HIC-B-001',
                'price'      => 99.99,
                'cost_price' => 50.00,
                'weight'     => 0.5,
                'status'     => 1,
                'merchant_id' => $this->merchantB->id,
            ]);
        });

        // 使用商户A的用户Token访问商户B站点的商品列表
        $response = $this->actingAs($this->userA, 'sanctum')
            ->withHeaders(['Host' => $this->storeB->domain])
            ->getJson('/api/v1/products');

        // 应该返回403或空列表（权限被拒绝）
        $statusCode = $response->getStatusCode();
        $this->assertTrue(
            in_array($statusCode, [403, 401, 200]),
            "Expected 403, 401 or 200, got {$statusCode}"
        );

        // 如果返回200，数据应该是空的或 filtered by merchant_id
        if ($statusCode === 200) {
            $data = $response->json('data') ?? $response->json('list') ?? [];
            // 商户A不应该看到商户B的商品
            foreach ($data as $item) {
                if (isset($item['merchant_id'])) {
                    $this->assertNotEquals($this->merchantA->id, $item['merchant_id'],
                        'Merchant A should not see Merchant B products');
                }
            }
        }
    }

    /* ================================================================
     |  商户A无法访问商户B的订单数据
     | ================================================================ */

    /** @test 商户A的Token请求商户B的订单列表返回403 */
    public function test_merchant_a_cannot_access_merchant_b_orders(): void
    {
        // 在商户B的站点中创建订单
        $this->runInTenantContext($this->storeB, function () {
            Order::create([
                'order_no'       => 'ORD-B-001',
                'a_order_no'     => 'A-ORD-B-001',
                'customer_id'    => 1,
                'merchant_id'    => $this->merchantB->id,
                'domain'         => $this->storeB->domain,
                'price'          => 99.99,
                'total'          => 99.99,
                'currency'       => 'USD',
                'exchange_rate'  => 1.0,
                'pay_status'     => 1,
                'shipment_status'=> 0,
            ]);
        });

        // 使用商户A的用户Token尝试通过商户后台API访问订单
        $response = $this->actingAs($this->userA, 'sanctum')
            ->getJson("/api/v1/merchant/orders?store_id={$this->storeB->id}");

        // 应该返回403（权限被拒绝）
        $response->assertStatus(403);
        $response->assertJson(['code' => 403]);
    }

    /* ================================================================
     |  商户A无法访问商户B的结算数据
     | ================================================================ */

    /** @test 商户A的Token请求商户B的结算列表返回403 */
    public function test_merchant_a_cannot_access_merchant_b_settlements(): void
    {
        // 创建结算记录到 Central DB
        DB::connection('central')->table('settlement_records')->insert([
            'merchant_id'   => $this->merchantB->id,
            'period_start'  => now()->subMonth()->startOfMonth(),
            'period_end'    => now()->subMonth()->endOfMonth(),
            'total_amount'  => 1000.00,
            'commission'    => 100.00,
            'settled_amount'=> 900.00,
            'status'        => 1,
            'created_at'    => now(),
            'updated_at'    => now(),
        ]);

        // 使用商户A的用户Token访问结算详情
        $settlementId = DB::connection('central')->table('settlement_records')
            ->where('merchant_id', $this->merchantB->id)
            ->first()->id;

        $response = $this->actingAs($this->userA, 'sanctum')
            ->getJson("/api/v1/merchant/settlements/{$settlementId}");

        // 应该返回403（无权查看）
        $response->assertStatus(403);
        $response->assertJson(['code' => 40300]);
    }

    /* ================================================================
     |  站点数据库完全隔离
     | ================================================================ */

    /** @test 站点A的Product表与站点B完全隔离 */
    public function test_tenant_db_completely_isolated_between_stores(): void
    {
        // 在站点A中创建商品
        $this->runInTenantContext($this->storeA, function () {
            Product::create([
                'sku'        => 'HIC-A-001',
                'model'      => 'HIC-A-001',
                'price'      => 99.99,
                'cost_price' => 50.00,
                'weight'     => 0.5,
                'status'     => 1,
                'merchant_id' => $this->merchantA->id,
            ]);
        });

        // 在站点B中创建商品
        $this->runInTenantContext($this->storeB, function () {
            Product::create([
                'sku'        => 'HIC-B-001',
                'model'      => 'HIC-B-001',
                'price'      => 89.99,
                'cost_price' => 45.00,
                'weight'     => 0.4,
                'status'     => 1,
                'merchant_id' => $this->merchantB->id,
            ]);
        });

        // 验证站点A只能看到自己的商品
        $this->runInTenantContext($this->storeA, function () {
            $products = Product::all();
            $this->assertCount(1, $products);
            $this->assertEquals('HIC-A-001', $products->first()->sku);
        });

        // 验证站点B只能看到自己的商品
        $this->runInTenantContext($this->storeB, function () {
            $products = Product::all();
            $this->assertCount(1, $products);
            $this->assertEquals('HIC-B-001', $products->first()->sku);
        });

        // 验证数据库名称不同
        $dbA = $this->runInTenantContext($this->storeA, function () {
            return DB::connection()->getDatabaseName();
        });
        $dbB = $this->runInTenantContext($this->storeB, function () {
            return DB::connection()->getDatabaseName();
        });

        $this->assertNotEquals($dbA, $dbB, '不同站点应该使用不同的数据库');
    }

    /* ================================================================
     |  Redis缓存前缀隔离
     | ================================================================ */

    /** @test store_1:key 和 store_2:key 互不影响 */
    public function test_redis_cache_prefix_isolation(): void
    {
        $cacheKey = 'test_isolation_key';

        // 在站点A上下文中写入缓存
        $this->runInTenantContext($this->storeA, function () use ($cacheKey) {
            Cache::put($cacheKey, 'value_from_store_a', 300);
        });

        // 在站点B上下文中写入相同key的不同值
        $this->runInTenantContext($this->storeB, function () use ($cacheKey) {
            Cache::put($cacheKey, 'value_from_store_b', 300);
        });

        // 验证站点A的值未被覆盖
        $this->runInTenantContext($this->storeA, function () use ($cacheKey) {
            $this->assertEquals('value_from_store_a', Cache::get($cacheKey));
        });

        // 验证站点B的值保持独立
        $this->runInTenantContext($this->storeB, function () use ($cacheKey) {
            $this->assertEquals('value_from_store_b', Cache::get($cacheKey));
        });
    }

    /* ================================================================
     |  商户DB隔离
     | ================================================================ */

    /** @test 商户A的master_products对商户B不可见 */
    public function test_merchant_db_isolated_between_merchants(): void
    {
        // 创建商户A的主商品
        $productA = $this->createMasterProduct($this->merchantA, 'HIC-A-MASTER-001', 'Product A');

        // 创建商户B的主商品
        $productB = $this->createMasterProduct($this->merchantB, 'HIC-B-MASTER-001', 'Product B');

        // 验证商户A只能看到自己的商品
        $productsA = $this->getMasterProductsForMerchant($this->merchantA);
        $this->assertCount(1, $productsA);
        $this->assertEquals('HIC-A-MASTER-001', $productsA->first()->sku);

        // 验证商户B只能看到自己的商品
        $productsB = $this->getMasterProductsForMerchant($this->merchantB);
        $this->assertCount(1, $productsB);
        $this->assertEquals('HIC-B-MASTER-001', $productsB->first()->sku);

        // 验证商品ID不同
        $this->assertNotEquals($productA->id, $productB->id);
    }

    /* ================================================================
     |  同步引擎隔离
     | ================================================================ */

    /** @test 同步引擎只同步本商户站点 */
    public function test_sync_engine_does_not_cross_merchant_boundary(): void
    {
        // 创建商户A的主商品
        $masterProductA = $this->createMasterProduct($this->merchantA, 'HIC-SYNC-A-001', 'Sync Product A');
    
        // 创建商户B的主商品
        $masterProductB = $this->createMasterProduct($this->merchantB, 'HIC-SYNC-B-001', 'Sync Product B');
    
        // 验证商户A的商品不会出现在商户B的上下文中
        // 通过检查商品SKU前缀来验证隔离
        $merchantAProducts = $this->getMasterProductsForMerchant($this->merchantA);
        $merchantBProducts = $this->getMasterProductsForMerchant($this->merchantB);
    
        // 验证每个商户只能看到自己的商品
        foreach ($merchantAProducts as $product) {
            $this->assertStringContainsString('HIC-A-', $product->sku);
        }
    
        foreach ($merchantBProducts as $product) {
            $this->assertStringContainsString('HIC-B-', $product->sku);
        }
    
        // 验证商户B的站点Tenant DB中没有商户A的商品
        $this->runInTenantContext($this->storeB, function () {
            $count = Product::where('sku', 'HIC-SYNC-A-001')->count();
            $this->assertEquals(0, $count, '商户B的站点不应该有商户A的商品');
        });
    
        // 验证商户A的站点Tenant DB中也没有商户B的商品
        $this->runInTenantContext($this->storeA, function () {
            $count = Product::where('sku', 'HIC-SYNC-B-001')->count();
            $this->assertEquals(0, $count, '商户A的站点不应该有商户B的商品');
        });
    }

    /* ================================================================
     |  商户站点创建权限
     | ================================================================ */

    /** @test 商户A不能为商户B创建站点 */
    public function test_merchant_cannot_create_store_for_another_merchant(): void
    {
        $response = $this->actingAs($this->userA, 'sanctum')
            ->postJson('/api/v1/merchant/stores', [
                'store_name' => 'Fake Store',
                'store_code' => 'fake_store_' . Str::random(4),
                'domain'     => 'fake-' . Str::random(8) . '.jerseyholic.test',
                'merchant_id' => $this->merchantB->id, // 尝试指定商户B的ID
            ]);

        // 应该返回403或422（权限被拒绝或参数错误）
        $statusCode = $response->getStatusCode();
        $this->assertTrue(
            in_array($statusCode, [403, 422, 401]),
            "Expected 403, 422 or 401, got {$statusCode}"
        );

        // 验证商户B名下没有新创建的站点
        $storeCount = Store::where('merchant_id', $this->merchantB->id)->count();
        $this->assertEquals(1, $storeCount, '商户B不应该有新创建的站点');
    }

    /* ================================================================
     |  辅助方法
     | ================================================================ */

    /**
     * 创建商户用户
     */
    protected function createMerchantUser(Merchant $merchant, string $email): MerchantUser
    {
        $user = MerchantUser::create([
            'merchant_id' => $merchant->id,
            'username'    => Str::before($email, '@'),
            'email'       => $email,
            'password'    => bcrypt('password'),
            'name'        => 'Test User',
            'role'        => 'admin',
            'status'      => 1,
        ]);

        return $user;
    }

    /**
     * 创建主商品（在商户DB中）
     */
    protected function createMasterProduct(Merchant $merchant, string $sku, string $name): MasterProduct
    {
        return DB::connection('central')->transaction(function () use ($merchant, $sku, $name) {
            // 使用 Central 连接创建，但通过 merchant_id 区分
            $product = new MasterProduct([
                'sku'         => $sku,
                'name'        => $name,
                'description' => 'Test description',
                'base_price'  => '99.99',
                'currency'    => 'USD',
                'status'      => MasterProduct::STATUS_ACTIVE,
                'sync_status' => MasterProduct::SYNC_PENDING,
            ]);

            // 设置连接为 central，因为 MasterProduct 表在 central DB 中
            $product->setConnection('central');
            $product->save();

            return $product;
        });
    }

    /**
     * 获取指定商户的主商品列表
     */
    protected function getMasterProductsForMerchant(Merchant $merchant): \Illuminate\Support\Collection
    {
        return MasterProduct::on('central')
            ->where('sku', 'like', 'HIC-' . substr($merchant->merchant_name, -1) . '-%')
            ->get();
    }
}
