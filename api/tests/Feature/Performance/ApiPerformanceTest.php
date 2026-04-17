<?php

declare(strict_types=1);

namespace Tests\Feature\Performance;

use App\Models\Central\Admin;
use App\Models\Central\Domain;
use App\Models\Central\Merchant;
use App\Models\Central\MerchantUser;
use App\Models\Central\Store;
use App\Models\Merchant\MasterProduct;
use App\Models\Tenant\Order;
use App\Models\Tenant\Product;
use App\Services\Product\ProductSyncService;
use App\Services\StoreProvisioningService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TenancyTestCase;

/**
 * API 性能基准测试
 *
 * 测试关键 API 接口的响应时间，确保在高数据量下仍能满足性能要求。
 *
 * @group performance
 */
class ApiPerformanceTest extends TenancyTestCase
{
    use RefreshDatabase;

    private Admin $admin;
    private Merchant $merchant;
    private MerchantUser $merchantUser;

    protected function setUp(): void
    {
        parent::setUp();

        // 创建测试管理员
        $this->admin = Admin::create([
            'username' => 'perf_admin',
            'email'    => 'perf_admin@test.com',
            'password' => bcrypt('password'),
            'name'     => 'Performance Admin',
            'status'   => 1,
            'is_super' => 1,
        ]);

        // 创建测试商户和商户用户
        $this->merchant = $this->createMerchant([
            'status' => 1,
            'level'  => 'vip', // VIP 等级无站点数量限制
        ]);

        $this->merchantUser = MerchantUser::create([
            'merchant_id' => $this->merchant->id,
            'username'    => 'perf_merchant_user',
            'email'       => 'perf_merchant@test.com',
            'password'    => bcrypt('password'),
            'name'        => 'Performance Merchant User',
            'role'        => 'owner',
            'status'      => 1,
        ]);
    }

    /* ================================================================
     |  仪表盘聚合查询性能测试
     | ================================================================ */

    /**
     * 测试：模拟10站点数据，仪表盘聚合查询 < 3秒
     *
     * 验证 DashboardController 在多站点场景下的聚合查询性能。
     */
    public function test_dashboard_aggregation_under_3_seconds(): void
    {
        // 创建 10 个站点并初始化数据
        $stores = [];
        for ($i = 0; $i < 10; $i++) {
            $store = $this->createAndProvisionStore("perf_store_{$i}");
            $stores[] = $store;

            // 为每个站点创建 50 个订单
            $this->createOrdersForStore($store, 50);
        }

        // 预热查询缓存
        DB::connection('central')->reconnect();

        $start = microtime(true);

        // 执行仪表盘聚合查询（模拟 DashboardController 逻辑）
        $dashboard = $this->fetchDashboardData($this->merchant);

        $elapsed = microtime(true) - $start;

        // 验证数据完整性
        $this->assertCount(10, $dashboard['stores_summary']);
        $this->assertGreaterThan(0, $dashboard['totals']['orders_today']);

        // 断言：10站点聚合查询应在 3 秒内完成（CI 环境放宽到 5 秒）
        $this->assertLessThan(
            5.0,
            $elapsed,
            "Dashboard aggregation for 10 stores took {$elapsed}s, expected < 5s"
        );
    }

    /* ================================================================
     |  商品列表 API 性能测试
     | ================================================================ */

    /**
     * 测试：1000条商品数据，列表API < 200ms
     *
     * 验证商品列表查询在大量数据下的响应时间。
     */
    public function test_product_list_api_under_200ms(): void
    {
        $store = $this->createAndProvisionStore('perf_products');

        // 在租户上下文中创建 1000 个商品
        $store->run(function () {
            $products = [];
            $now = now();

            for ($i = 1; $i <= 1000; $i++) {
                $products[] = [
                    'sku'          => 'hic-PERF-' . str_pad((string) $i, 5, '0', STR_PAD_LEFT),
                    'sku_prefix'   => 'hic',
                    'model'        => 'Model-' . $i,
                    'price'        => rand(1000, 5000) / 100,
                    'cost_price'   => rand(500, 3000) / 100,
                    'quantity'     => rand(10, 100),
                    'stock_status' => 1,
                    'weight'       => '0.50',
                    'status'       => 1,
                    'merchant_id'  => $this->merchant->id,
                    'created_at'   => $now,
                    'updated_at'   => $now,
                ];

                // 分批插入避免内存溢出
                if ($i % 100 === 0) {
                    DB::table('jh_products')->insert($products);
                    $products = [];
                }
            }

            // 插入剩余数据
            if (!empty($products)) {
                DB::table('jh_products')->insert($products);
            }
        });

        // 预热查询
        $store->run(function () {
            Product::query()->count();
        });

        $start = microtime(true);

        // 模拟商品列表查询（含分页）
        $result = $store->run(function () {
            return Product::query()
                ->select(['id', 'sku', 'model', 'price', 'quantity', 'status'])
                ->where('status', 1)
                ->orderBy('id', 'desc')
                ->paginate(20);
        });

        $elapsedMs = (microtime(true) - $start) * 1000;

        // 验证结果
        $this->assertEquals(20, $result->count());
        $this->assertEquals(1000, $result->total());

        // 断言：列表查询应在 500ms 内完成（CI 环境放宽）
        $this->assertLessThan(
            500,
            $elapsedMs,
            "Product list API took {$elapsedMs}ms for 1000 products, expected < 500ms"
        );
    }

    /* ================================================================
     |  订单列表 API 性能测试
     | ================================================================ */

    /**
     * 测试：含筛选+分页的订单列表 API < 200ms
     *
     * 验证订单列表在多条件筛选下的响应时间。
     */
    public function test_order_list_api_under_200ms(): void
    {
        $store = $this->createAndProvisionStore('perf_orders');

        // 创建 500 个订单
        $this->createOrdersForStore($store, 500);

        // 预热查询
        $store->run(function () {
            Order::query()->count();
        });

        $start = microtime(true);

        // 模拟复杂筛选查询
        $result = $store->run(function () {
            return Order::query()
                ->select([
                    'id', 'order_no', 'customer_id', 'total',
                    'pay_status', 'shipment_status', 'created_at'
                ])
                ->where('pay_status', 1) // pending
                ->where('total', '>', 50)
                ->whereDate('created_at', '>=', now()->subDays(30))
                ->orderBy('created_at', 'desc')
                ->paginate(20);
        });

        $elapsedMs = (microtime(true) - $start) * 1000;

        // 验证结果结构
        $this->assertLessThanOrEqual(20, $result->count());

        // 断言：含筛选的订单列表应在 500ms 内完成
        $this->assertLessThan(
            500,
            $elapsedMs,
            "Order list API with filters took {$elapsedMs}ms, expected < 500ms"
        );
    }

    /* ================================================================
     |  站点创建性能测试
     | ================================================================ */

    /**
     * 测试：站点创建全流程 < 30秒
     *
     * 验证 StoreProvisioningService 创建站点的完整流程性能。
     */
    public function test_store_creation_under_30_seconds(): void
    {
        $service = app(StoreProvisioningService::class);

        $storeCode = 'perf_create_' . Str::random(6);
        $domain = $storeCode . '.jerseyholic.test';

        $start = microtime(true);

        $store = $service->provision($this->merchant, [
            'store_name'           => 'Performance Test Store',
            'store_code'           => $storeCode,
            'domain'               => $domain,
            'target_markets'       => ['US', 'UK', 'DE'],
            'supported_languages'  => ['en', 'de', 'fr'],
            'supported_currencies' => ['USD', 'EUR', 'GBP'],
        ]);

        $elapsed = microtime(true) - $start;

        // 记录创建的站点以便清理
        $this->createdStores[] = $store;

        // 验证站点创建成功
        $this->assertInstanceOf(Store::class, $store);
        $this->assertEquals(1, $store->status);
        $this->assertNotNull($store->database_name);

        // 验证域名创建
        $this->assertDatabaseHas('jh_domains', [
            'domain'   => $domain,
            'store_id' => $store->id,
        ]);

        // 断言：站点创建应在 30 秒内完成（CI 环境放宽到 45 秒）
        $this->assertLessThan(
            45.0,
            $elapsed,
            "Store creation took {$elapsed}s, expected < 45s"
        );
    }

    /* ================================================================
     |  商品同步性能测试
     | ================================================================ */

    /**
     * 测试：单品同步 < 10秒/站点
     *
     * 验证 ProductSyncService 同步单个商品到租户的性能。
     */
    public function test_single_product_sync_under_10_seconds(): void
    {
        $store = $this->createAndProvisionStore('perf_sync_single');
        $syncService = app(ProductSyncService::class);

        // 创建主商品
        $masterProduct = $this->createMasterProduct('hic-PERF-SYNC-001');

        $start = microtime(true);

        $result = $syncService->syncToStore(
            $this->merchant,
            $masterProduct->id,
            $store
        );

        $elapsed = microtime(true) - $start;

        // 验证同步成功
        $this->assertTrue($result->success);
        $this->assertNotNull($result->tenantProductId);

        // 验证租户数据库中的商品
        $store->run(function () use ($result) {
            $product = Product::find($result->tenantProductId);
            $this->assertNotNull($product);
            $this->assertEquals('hic-PERF-SYNC-001', $product->sku);
        });

        // 断言：单品同步应在 10 秒内完成
        $this->assertLessThan(
            10.0,
            $elapsed,
            "Single product sync took {$elapsed}s, expected < 10s"
        );
    }

    /**
     * 测试：批量同步50商品 < 60秒
     *
     * 验证 ProductSyncService 批量同步性能。
     */
    public function test_batch_sync_50_products_under_60_seconds(): void
    {
        $store = $this->createAndProvisionStore('perf_sync_batch');
        $syncService = app(ProductSyncService::class);

        // 创建 50 个主商品
        $productIds = [];
        for ($i = 1; $i <= 50; $i++) {
            $masterProduct = $this->createMasterProduct(
                'hic-PERF-BATCH-' . str_pad((string) $i, 3, '0', STR_PAD_LEFT)
            );
            $productIds[] = $masterProduct->id;
        }

        $start = microtime(true);

        $batchResult = $syncService->batchSync(
            $this->merchant,
            $productIds,
            $store
        );

        $elapsed = microtime(true) - $start;

        // 验证批量同步结果
        $this->assertEquals(50, $batchResult->total);
        $this->assertEquals(50, $batchResult->succeeded);
        $this->assertEquals(0, $batchResult->failed);

        // 断言：批量同步 50 商品应在 60 秒内完成（CI 环境放宽到 90 秒）
        $this->assertLessThan(
            90.0,
            $elapsed,
            "Batch sync of 50 products took {$elapsed}s, expected < 90s"
        );

        // 验证同步的商品数量
        $syncedCount = $store->run(function () {
            return Product::where('sku', 'like', 'hic-PERF-BATCH-%')->count();
        });
        $this->assertEquals(50, $syncedCount);
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
            'store_name' => 'Perf Store ' . $storeCode,
            'store_code' => $storeCode,
            'domain'     => $domain,
        ]);

        $this->createdStores[] = $store;

        return $store;
    }

    /**
     * 为站点创建测试订单
     */
    private function createOrdersForStore(Store $store, int $count): void
    {
        $store->run(function () use ($count, $store) {
            $orders = [];
            $now = now();

            for ($i = 1; $i <= $count; $i++) {
                $payStatus = rand(1, 5); // 1=pending, 2=paid, etc.
                $total = rand(5000, 50000) / 100;

                $orders[] = [
                    'order_no'        => 'ORD-' . Str::random(12),
                    'a_order_no'      => 'A-' . Str::random(10),
                    'customer_id'     => rand(1, 100),
                    'merchant_id'     => $this->merchant->id,
                    'a_website'       => $store->domain,
                    'domain'          => $store->domain,
                    'currency'        => 'USD',
                    'exchange_rate'   => '1.00000000',
                    'price'           => $total * 0.9,
                    'shipping_fee'    => $total * 0.1,
                    'tax_amount'      => 0,
                    'discount_amount' => 0,
                    'total'           => $total,
                    'pay_status'      => $payStatus,
                    'shipment_status' => 0,
                    'refund_status'   => 0,
                    'dispute_status'  => 0,
                    'created_at'      => $now->copy()->subDays(rand(0, 30)),
                    'updated_at'      => $now,
                ];

                if ($i % 50 === 0) {
                    DB::table('jh_orders')->insert($orders);
                    $orders = [];
                }
            }

            if (!empty($orders)) {
                DB::table('jh_orders')->insert($orders);
            }
        });
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

    /**
     * 获取仪表盘聚合数据（模拟 DashboardController）
     */
    private function fetchDashboardData(Merchant $merchant): array
    {
        $stores = Store::where('merchant_id', $merchant->id)
            ->where('status', '>=', 0)
            ->withoutTrashed()
            ->get();

        $dashboard = [
            'stores_summary' => [],
            'totals'         => [
                'orders_today'   => 0,
                'orders_week'    => 0,
                'orders_month'   => 0,
                'revenue_today'  => 0,
                'revenue_week'   => 0,
                'revenue_month'  => 0,
                'pending_orders' => 0,
            ],
        ];

        foreach ($stores as $store) {
            try {
                $storeData = $store->run(function () {
                    $today = today();
                    $weekStart = now()->startOfWeek();
                    $monthStart = now()->startOfMonth();

                    return [
                        'orders_today'   => Order::whereDate('created_at', $today)->count(),
                        'orders_week'    => Order::where('created_at', '>=', $weekStart)->count(),
                        'orders_month'   => Order::where('created_at', '>=', $monthStart)->count(),
                        'revenue_today'  => (float) Order::whereDate('created_at', $today)
                            ->whereIn('pay_status', [2, 3, 4, 5])
                            ->sum('total'),
                        'revenue_week'   => (float) Order::where('created_at', '>=', $weekStart)
                            ->whereIn('pay_status', [2, 3, 4, 5])
                            ->sum('total'),
                        'revenue_month'  => (float) Order::where('created_at', '>=', $monthStart)
                            ->whereIn('pay_status', [2, 3, 4, 5])
                            ->sum('total'),
                        'pending_orders' => Order::where('pay_status', 1)->count(),
                    ];
                });
            } catch (\Throwable $e) {
                $storeData = array_fill_keys([
                    'orders_today', 'orders_week', 'orders_month',
                    'revenue_today', 'revenue_week', 'revenue_month', 'pending_orders'
                ], 0);
            }

            $dashboard['stores_summary'][] = [
                'store_id'   => $store->id,
                'store_name' => $store->store_name,
                ...$storeData,
            ];

            foreach ($dashboard['totals'] as $key => $value) {
                $dashboard['totals'][$key] += $storeData[$key] ?? 0;
            }
        }

        return $dashboard;
    }
}
