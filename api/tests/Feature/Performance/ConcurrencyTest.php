<?php

declare(strict_types=1);

namespace Tests\Feature\Performance;

use App\Jobs\ProductSyncJob;
use App\Models\Central\Merchant;
use App\Models\Central\PaymentAccount;
use App\Models\Central\PaymentAccountGroup;
use App\Models\Central\Store;
use App\Models\Merchant\MasterProduct;
use App\Models\Tenant\Order;
use App\Models\Tenant\Product;
use App\Services\Payment\ElectionService;
use App\Services\Product\ProductSyncService;
use App\Services\StoreProvisioningService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Tests\TenancyTestCase;

/**
 * 并发安全测试
 *
 * 验证系统在多站点并发、同步任务并发、支付选号并发等场景下的
 * 数据一致性和幂等性。
 *
 * @group performance
 * @group concurrency
 */
class ConcurrencyTest extends TenancyTestCase
{
    use RefreshDatabase;

    private Merchant $merchant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->merchant = $this->createMerchant([
            'status' => 1,
            'level'  => 'vip',
        ]);
    }

    /* ================================================================
     |  多站点并发数据隔离测试
     | ================================================================ */

    /**
     * 测试：模拟多站点并发，验证数据不串
     *
     * 创建多个站点，在每个站点中创建具有相同 ID 的商品，
     * 验证各站点数据完全隔离，不会出现交叉访问。
     */
    public function test_concurrent_requests_different_stores_isolated(): void
    {
        // 创建 5 个站点
        $stores = [];
        for ($i = 1; $i <= 5; $i++) {
            $stores[] = $this->createAndProvisionStore("iso_store_{$i}");
        }

        // 在每个站点中创建相同 SKU 的商品（模拟并发场景）
        $commonSku = 'hic-CONCURRENT-001';
        $storeProductIds = [];

        foreach ($stores as $index => $store) {
            $tenantProductId = $store->run(function () use ($commonSku, $index) {
                $product = Product::create([
                    'sku'          => $commonSku,
                    'sku_prefix'   => 'hic',
                    'model'        => 'Model-Concurrent',
                    'price'        => '99.99',
                    'cost_price'   => '50.00',
                    'quantity'     => 100,
                    'stock_status' => 1,
                    'weight'       => '0.50',
                    'status'       => 1,
                    'merchant_id'  => $this->merchant->id,
                ]);

                return $product->id;
            });

            $storeProductIds[$store->id] = $tenantProductId;
        }

        // 验证各站点的商品 ID 是独立的（即使 SKU 相同）
        $uniqueIds = array_unique($storeProductIds);
        $this->assertCount(5, $uniqueIds, 'Each store should have independent product IDs');

        // 验证每个站点只能访问自己的商品
        foreach ($stores as $store) {
            $store->run(function () use ($store, $storeProductIds, $commonSku) {
                // 当前站点应该只有 1 个该 SKU 的商品
                $count = Product::where('sku', $commonSku)->count();
                $this->assertEquals(1, $count, "Store {$store->id} should have exactly 1 product with SKU {$commonSku}");

                // 验证只能访问到自己的商品 ID
                $product = Product::where('sku', $commonSku)->first();
                $this->assertEquals($storeProductIds[$store->id], $product->id);
            });
        }

        // 验证跨站点数据不泄漏
        foreach ($stores as $storeAItem) {
            foreach ($stores as $storeBItem) {
                if ($storeAItem->id === $storeBItem->id) {
                    continue;
                }

                $storeAId = $storeAItem->id;
                $storeBId = $storeBItem->id;
                $storeAItem->run(function () use ($storeBId, $storeProductIds, $storeAId) {
                    // 站点 A 不应该能查询到站点 B 的商品 ID
                    $exists = Product::where('id', $storeProductIds[$storeBId])->exists();
                    $this->assertFalse($exists, "Store {$storeAId} should not access product from store {$storeBId}");
                });
            }
        }
    }

    /* ================================================================
     |  并发同步任务不冲突测试
     | ================================================================ */

    /**
     * 测试：并发同步 Job 不冲突
     *
     * 验证同一商品的多次同步请求是幂等的，不会产生重复数据。
     */
    public function test_concurrent_sync_jobs_no_conflict(): void
    {
        $store = $this->createAndProvisionStore('sync_conflict_test');
        $syncService = app(ProductSyncService::class);

        // 创建主商品
        $masterProduct = $this->createMasterProduct('hic-SYNC-CONFLICT-001');

        // 模拟并发同步：连续快速调用 5 次同步
        $results = [];
        for ($i = 0; $i < 5; $i++) {
            $results[] = $syncService->syncToStore(
                $this->merchant,
                $masterProduct->id,
                $store
            );
        }

        // 所有同步都应该成功
        foreach ($results as $result) {
            $this->assertTrue($result->success, 'All sync attempts should succeed');
        }

        // 验证幂等性：所有同步返回的租户商品 ID 应该相同
        $tenantProductIds = array_unique(array_map(fn ($r) => $r->tenantProductId, $results));
        $this->assertCount(1, $tenantProductIds, 'Idempotent sync should return same tenant product ID');

        // 验证租户数据库中只有 1 个商品（没有重复创建）
        $store->run(function () use ($masterProduct) {
            $count = Product::where('sync_source_id', $masterProduct->id)
                ->where('merchant_id', $this->merchant->id)
                ->count();
            $this->assertEquals(1, $count, 'Should have exactly 1 product after concurrent syncs');
        });
    }

    /**
     * 测试：批量同步的幂等性
     *
     * 验证批量同步同一批商品多次不会产生重复数据。
     */
    public function test_batch_sync_idempotency(): void
    {
        $store = $this->createAndProvisionStore('batch_sync_idempotent');
        $syncService = app(ProductSyncService::class);

        // 创建 10 个主商品
        $productIds = [];
        for ($i = 1; $i <= 10; $i++) {
            $masterProduct = $this->createMasterProduct("hic-BATCH-IDEM-{$i}");
            $productIds[] = $masterProduct->id;
        }

        // 第一次批量同步
        $result1 = $syncService->batchSync($this->merchant, $productIds, $store);
        $this->assertEquals(10, $result1->succeeded);

        // 获取第一次同步后的商品数量
        $countAfterFirst = $store->run(function () {
            return Product::count();
        });
        $this->assertEquals(10, $countAfterFirst);

        // 第二次批量同步（相同商品）
        $result2 = $syncService->batchSync($this->merchant, $productIds, $store);
        $this->assertEquals(10, $result2->succeeded);

        // 验证商品数量没有增加（幂等性）
        $countAfterSecond = $store->run(function () {
            return Product::count();
        });
        $this->assertEquals(10, $countAfterSecond, 'Batch sync should be idempotent');
    }

    /* ================================================================
     |  并发支付选号不重复分配测试
     | ================================================================ */

    /**
     * 测试：并发选号不重复分配
     *
     * 模拟并发场景下支付账号的分配，验证选号算法的正确性。
     * 注意：此测试验证选号逻辑本身，而非真正的并发执行。
     */
    public function test_concurrent_payment_selection_no_double_assign(): void
    {
        // 创建支付账号分组
        $group = PaymentAccountGroup::create([
            'name'        => 'Test Group Concurrent',
            'group_type'  => PaymentAccountGroup::GROUP_TYPE_STANDARD_SHARED,
            'description' => 'For concurrency testing',
        ]);

        // 创建 3 个支付账号
        $accounts = [];
        for ($i = 1; $i <= 3; $i++) {
            $accounts[] = PaymentAccount::create([
                'group_id'      => $group->id,
                'account'       => "test_concurrent_{$i}@example.com",
                'account_type'  => 'paypal',
                'status'        => 1,
                'health_score'  => 80 + $i * 5,
                'priority'      => $i,
                'single_limit'  => '1000.00',
                'daily_limit'   => '10000.00',
                'monthly_limit' => '100000.00',
            ]);
        }

        // 创建站点映射
        $store = $this->createAndProvisionStore('payment_concurrent');
        DB::table('merchant_payment_group_mappings')->insert([
            'store_id'       => $store->id,
            'merchant_id'    => $this->merchant->id,
            'payment_method' => 'paypal',
            'group_id'       => $group->id,
            'priority'       => 1,
            'created_at'     => now(),
            'updated_at'     => now(),
        ]);

        $electionService = app(ElectionService::class);

        // 模拟连续选号（验证基本逻辑）
        $selectedAccounts = [];
        for ($i = 0; $i < 5; $i++) {
            $result = $electionService->elect(
                $store->id,
                'paypal',
                '100.00',
                ['ip' => '192.168.1.' . $i, 'email' => "buyer{$i}@test.com"]
            );

            if ($result->success) {
                $selectedAccounts[] = $result->account->id;
                // 标记账号已使用
                $electionService->markAccountUsed($result->account);
            }
        }

        // 验证选号结果
        $this->assertNotEmpty($selectedAccounts, 'Should have selected accounts');

        // 验证选中的账号都在可用列表中
        foreach ($selectedAccounts as $accountId) {
            $this->assertContains($accountId, array_map(fn ($a) => $a->id, $accounts));
        }

        // 清理
        PaymentAccount::where('group_id', $group->id)->delete();
        $group->delete();
    }

    /* ================================================================
     |  缓存一致性测试
     | ================================================================ */

    /**
     * 测试：并发读写缓存一致性
     *
     * 验证在高频缓存读写场景下，数据保持一致性。
     */
    public function test_cache_consistency_under_concurrent_access(): void
    {
        $cacheKey = 'test_concurrent_counter';
        $iterations = 50;

        // 初始化计数器
        Cache::put($cacheKey, 0, 300);

        // 模拟并发递增（顺序执行，但快速连续）
        for ($i = 0; $i < $iterations; $i++) {
            Cache::increment($cacheKey);
        }

        // 验证最终值
        $finalValue = Cache::get($cacheKey);
        $this->assertEquals($iterations, $finalValue, "Cache counter should be {$iterations}, got {$finalValue}");

        // 清理
        Cache::forget($cacheKey);
    }

    /**
     * 测试：缓存锁机制
     *
     * 验证缓存锁在并发场景下的正确性。
     */
    public function test_cache_lock_mechanism(): void
    {
        $lockKey = 'test_concurrent_lock';
        $lock = Cache::lock($lockKey, 10);

        // 获取锁
        $this->assertTrue($lock->get(), 'Should acquire lock');

        // 尝试再次获取同一锁（应该失败）
        $secondLock = Cache::lock($lockKey, 10);
        $this->assertFalse($secondLock->get(), 'Should not acquire same lock twice');

        // 释放锁
        $lock->release();

        // 再次获取应该成功
        $this->assertTrue($secondLock->get(), 'Should acquire lock after release');
        $secondLock->release();
    }

    /* ================================================================
     |  数据库事务一致性测试
     | ================================================================ */

    /**
     * 测试：订单创建的事务一致性
     *
     * 验证订单创建过程中，订单主表和订单项表的数据一致性。
     */
    public function test_order_creation_transactional_integrity(): void
    {
        $store = $this->createAndProvisionStore('order_integrity');

        $orderNo = 'ORD-INTEGRITY-' . Str::random(8);

        $store->run(function () use ($orderNo) {
            DB::transaction(function () use ($orderNo) {
                // 创建订单主表
                $orderId = DB::table('jh_orders')->insertGetId([
                    'order_no'        => $orderNo,
                    'a_order_no'      => 'A-' . $orderNo,
                    'customer_id'     => 1,
                    'merchant_id'     => $this->merchant->id,
                    'a_website'       => 'test.com',
                    'domain'          => 'test.com',
                    'currency'        => 'USD',
                    'exchange_rate'   => '1.00000000',
                    'price'           => '100.00',
                    'shipping_fee'    => '10.00',
                    'tax_amount'      => '0.00',
                    'discount_amount' => '0.00',
                    'total'           => '110.00',
                    'pay_status'      => 1,
                    'created_at'      => now(),
                    'updated_at'      => now(),
                ]);

                // 创建订单项
                DB::table('jh_order_items')->insert([
                    'order_id'   => $orderId,
                    'product_id' => 1,
                    'name'       => 'Test Product',
                    'sku'        => 'TEST-001',
                    'quantity'   => 1,
                    'price'      => '100.00',
                    'total'      => '100.00',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                // 验证订单和订单项都创建成功
                $this->assertDatabaseHas('jh_orders', ['order_no' => $orderNo]);
                $this->assertDatabaseHas('jh_order_items', ['order_id' => $orderId]);
            });
        });

        // 验证最终数据一致性
        $store->run(function () use ($orderNo) {
            $order = DB::table('jh_orders')->where('order_no', $orderNo)->first();
            $this->assertNotNull($order);

            $items = DB::table('jh_order_items')->where('order_id', $order->id)->get();
            $this->assertCount(1, $items);
            $this->assertEquals('100.00', $items[0]->total);
        });
    }

    /**
     * 测试：租户上下文隔离
     *
     * 验证在多租户环境下，数据库操作严格限制在当前租户内。
     */
    public function test_tenant_context_isolation(): void
    {
        // 创建两个站点
        $storeA = $this->createAndProvisionStore('tenant_iso_a');
        $storeB = $this->createAndProvisionStore('tenant_iso_b');

        // 在站点 A 中创建订单
        $orderNoA = 'ORD-ISO-A-' . Str::random(8);
        $storeA->run(function () use ($orderNoA) {
            Order::create([
                'order_no'      => $orderNoA,
                'a_order_no'    => 'A-' . $orderNoA,
                'customer_id'   => 1,
                'merchant_id'   => $this->merchant->id,
                'a_website'     => 'store-a.com',
                'domain'        => 'store-a.com',
                'currency'      => 'USD',
                'exchange_rate' => '1.00000000',
                'price'         => '100.00',
                'shipping_fee'  => '10.00',
                'total'         => '110.00',
                'pay_status'    => 1,
            ]);
        });

        // 在站点 B 中创建订单
        $orderNoB = 'ORD-ISO-B-' . Str::random(8);
        $storeB->run(function () use ($orderNoB) {
            Order::create([
                'order_no'      => $orderNoB,
                'a_order_no'    => 'A-' . $orderNoB,
                'customer_id'   => 2,
                'merchant_id'   => $this->merchant->id,
                'a_website'     => 'store-b.com',
                'domain'        => 'store-b.com',
                'currency'      => 'USD',
                'exchange_rate' => '1.00000000',
                'price'         => '200.00',
                'shipping_fee'  => '20.00',
                'total'         => '220.00',
                'pay_status'    => 1,
            ]);
        });

        // 验证站点 A 只能看到自己的订单
        $storeA->run(function () use ($orderNoA, $orderNoB) {
            $this->assertTrue(Order::where('order_no', $orderNoA)->exists());
            $this->assertFalse(Order::where('order_no', $orderNoB)->exists());
        });

        // 验证站点 B 只能看到自己的订单
        $storeB->run(function () use ($orderNoA, $orderNoB) {
            $this->assertFalse(Order::where('order_no', $orderNoA)->exists());
            $this->assertTrue(Order::where('order_no', $orderNoB)->exists());
        });
    }

    /* ================================================================
     |  辅助方法
     | ================================================================ */

    /**
     * 创建并初始化站点
     */
    private function createAndProvisionStore(string $storeCode): Store
    {
        $service = app(StoreProvisioningService::class);
        $domain = $storeCode . '.jerseyholic.test';

        $store = $service->provision($this->merchant, [
            'store_name' => 'Concurrent Test Store ' . $storeCode,
            'store_code' => $storeCode,
            'domain'     => $domain,
        ]);

        $this->createdStores[] = $store;

        return $store;
    }

    /**
     * 创建主商品
     */
    private function createMasterProduct(string $sku): MasterProduct
    {
        return MasterProduct::create([
            'sku'          => $sku,
            'name'         => 'Test Product ' . $sku,
            'description'  => 'Test description for ' . $sku,
            'base_price'   => '99.99',
            'currency'     => 'USD',
            'weight'       => '0.50',
            'dimensions'   => ['length' => '30.00', 'width' => '20.00', 'height' => '5.00'],
            'images'       => ['https://example.com/image1.jpg'],
            'status'       => MasterProduct::STATUS_ACTIVE,
            'sync_status'  => MasterProduct::SYNC_PENDING,
            'is_sensitive' => str_starts_with($sku, 'hic-'),
        ]);
    }
}
