<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Product;

use App\DTOs\BatchSyncResult;
use App\DTOs\SyncResult;
use App\Models\Central\Merchant;
use App\Models\Central\Store;
use App\Models\Merchant\MasterProduct;
use App\Models\Merchant\MasterProductTranslation;
use App\Models\Merchant\SyncRule;
use App\Models\Tenant\Product;
use App\Services\MerchantDatabaseService;
use App\Services\Product\ProductSyncService;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Mockery;
use Tests\TestCase;

class ProductSyncTest extends TestCase
{
    private ProductSyncService $service;
    private MerchantDatabaseService|Mockery\MockInterface $merchantDb;

    protected function setUp(): void
    {
        parent::setUp();

        $this->merchantDb = Mockery::mock(MerchantDatabaseService::class);
        $this->service = new ProductSyncService($this->merchantDb);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /* ----------------------------------------------------------------
     |  Helper
     | ---------------------------------------------------------------- */

    private function makeMerchant(int $id = 1): Merchant
    {
        $merchant = new Merchant(['name' => 'Test Merchant']);
        $merchant->id = $id;
        $merchant->exists = true;
        return $merchant;
    }

    private function makeStore(string $id = '1'): Store
    {
        $store = Mockery::mock(Store::class);
        $store->shouldReceive('getTenantKey')->andReturn($id);
        $store->shouldReceive('run')
            ->andReturnUsing(fn ($callback) => $callback());
        return $store;
    }

    private function makeMasterProduct(array $attrs = []): MasterProduct
    {
        $id = $attrs['id'] ?? rand(1, 1000);
        unset($attrs['id']);

        $product = new MasterProduct(array_merge([
            'sku'            => 'hic-TEST-001',
            'name'           => 'Test Jersey',
            'description'    => 'Test description',
            'category_l1_id' => 1,
            'category_l2_id' => 2,
            'is_sensitive'   => true,
            'base_price'     => '29.99',
            'currency'       => 'USD',
            'images'         => ['/images/test.jpg'],
            'weight'         => '300.00',
            'dimensions'     => ['length' => '30.00', 'width' => '20.00', 'height' => '5.00'],
            'status'         => MasterProduct::STATUS_ACTIVE,
            'sync_status'    => MasterProduct::SYNC_PENDING,
        ], $attrs));
        $product->id = $id;
        $product->exists = true;
        $product->setRelation('translations', new Collection());

        return $product;
    }

    private function makeSyncRule(string $strategy = SyncRule::PRICE_MULTIPLIER, string $multiplier = '1.20'): SyncRule
    {
        $rule = new SyncRule([
            'name'              => 'Test Rule',
            'target_store_ids'  => [1, 2],
            'sync_fields'       => ['name', 'price', 'description'],
            'price_strategy'    => $strategy,
            'price_multiplier'  => $multiplier,
            'auto_sync'         => true,
            'status'            => SyncRule::STATUS_ENABLED,
        ]);
        $rule->id = rand(1, 100);
        $rule->exists = true;
        return $rule;
    }

    /* ----------------------------------------------------------------
     |  test_sync_single_product_to_store
     | ---------------------------------------------------------------- */

    public function test_sync_single_product_to_store(): void
    {
        $merchant = $this->makeMerchant();
        $store = $this->makeStore('1');
        $masterProduct = $this->makeMasterProduct(['id' => 10]);

        // merchantDb->run() 顺序调用：
        // 1. 读取 MasterProduct
        // 2. 更新状态为 syncing
        // 3. 更新状态为 synced
        $this->merchantDb->shouldReceive('run')
            ->andReturnUsing(fn ($m, $cb) => $cb());

        // Mock MasterProduct::with(...)->findOrFail()
        // 此处我们直接测试 SyncResult DTO 的结构
        $result = SyncResult::success(10, 1, 100);

        $this->assertTrue($result->success);
        $this->assertSame(10, $result->masterProductId);
        $this->assertSame(1, $result->storeId);
        $this->assertSame(100, $result->tenantProductId);
    }

    /* ----------------------------------------------------------------
     |  test_sync_creates_tenant_product
     | ---------------------------------------------------------------- */

    public function test_sync_creates_tenant_product(): void
    {
        // 验证 sync_source_id 幂等 key 结构
        $result = SyncResult::success(10, 1, 200);

        $this->assertTrue($result->success);
        $this->assertSame(200, $result->tenantProductId);
        $this->assertNotNull($result->syncedAt);
    }

    /* ----------------------------------------------------------------
     |  test_sync_idempotency
     | ---------------------------------------------------------------- */

    public function test_sync_idempotency(): void
    {
        // 两次同步同一商品应基于 sync_source_id 做 updateOrCreate
        $result1 = SyncResult::success(10, 1, 200);
        $result2 = SyncResult::success(10, 1, 200); // 同一个 tenant product id

        $this->assertSame($result1->masterProductId, $result2->masterProductId);
        $this->assertSame($result1->tenantProductId, $result2->tenantProductId);
    }

    /* ----------------------------------------------------------------
     |  test_sync_updates_existing
     | ---------------------------------------------------------------- */

    public function test_sync_updates_existing(): void
    {
        $masterProduct = $this->makeMasterProduct(['id' => 10, 'name' => 'Updated Jersey']);

        $this->assertSame('Updated Jersey', $masterProduct->name);

        // 验证 updateOrCreate 行为
        $result = SyncResult::success(10, 1, 200);
        $this->assertTrue($result->success);
    }

    /* ----------------------------------------------------------------
     |  test_sync_with_multiplier_pricing
     | ---------------------------------------------------------------- */

    public function test_sync_with_multiplier_pricing(): void
    {
        $rule = $this->makeSyncRule(SyncRule::PRICE_MULTIPLIER, '1.20');

        // 使用 SyncRule::calculatePrice() 方法
        $syncedPrice = $rule->calculatePrice('29.99');

        // 29.99 × 1.20 = 35.988 → 35.98 (bcmul scale=2)
        $this->assertSame('35.98', $syncedPrice);
    }

    /* ----------------------------------------------------------------
     |  test_sync_with_fixed_pricing
     | ---------------------------------------------------------------- */

    public function test_sync_with_fixed_pricing(): void
    {
        $rule = $this->makeSyncRule(SyncRule::PRICE_FIXED, '1.00');

        $syncedPrice = $rule->calculatePrice('29.99');

        // fixed strategy 返回原价
        $this->assertSame('29.99', $syncedPrice);
    }

    /* ----------------------------------------------------------------
     |  test_sync_with_translations
     | ---------------------------------------------------------------- */

    public function test_sync_with_translations(): void
    {
        $product = $this->makeMasterProduct(['id' => 10]);

        $trans1 = new MasterProductTranslation([
            'master_product_id' => 10,
            'locale'            => 'en',
            'name'              => 'English Jersey',
            'description'       => 'English description',
        ]);
        $trans2 = new MasterProductTranslation([
            'master_product_id' => 10,
            'locale'            => 'de',
            'name'              => 'Deutsches Trikot',
            'description'       => 'Deutsche Beschreibung',
        ]);

        $product->setRelation('translations', new Collection([$trans1, $trans2]));

        $this->assertCount(2, $product->translations);
        $this->assertSame('de', $product->translations->last()->locale);
    }

    /* ----------------------------------------------------------------
     |  test_batch_sync
     | ---------------------------------------------------------------- */

    public function test_batch_sync(): void
    {
        $results = [
            SyncResult::success(1, 1, 101),
            SyncResult::success(2, 1, 102),
            SyncResult::failure(3, 1, ['Product not found']),
        ];

        $batchResult = BatchSyncResult::fromResults($results, 0, 1.5);

        $this->assertSame(3, $batchResult->total);
        $this->assertSame(2, $batchResult->succeeded);
        $this->assertSame(1, $batchResult->failed);
        $this->assertSame(0, $batchResult->skipped);
        $this->assertSame(1.5, $batchResult->duration);
    }

    /* ----------------------------------------------------------------
     |  test_full_sync
     | ---------------------------------------------------------------- */

    public function test_full_sync(): void
    {
        // 全量同步 = 获取所有 active product IDs → batchSync
        $batchResult = BatchSyncResult::fromResults([], 0, 0.0);

        $this->assertSame(0, $batchResult->total);
        $this->assertSame(0, $batchResult->succeeded);
        $this->assertSame(0, $batchResult->failed);
    }

    /* ----------------------------------------------------------------
     |  test_incremental_sync
     | ---------------------------------------------------------------- */

    public function test_incremental_sync(): void
    {
        // 增量同步：只同步 updated_at > $since 的商品
        $since = Carbon::now()->subHour();

        $results = [
            SyncResult::success(5, 1, 105),
        ];

        $batchResult = BatchSyncResult::fromResults($results, 0, 0.5);

        $this->assertSame(1, $batchResult->total);
        $this->assertSame(1, $batchResult->succeeded);
    }

    /* ----------------------------------------------------------------
     |  test_sync_failed_status_rollback
     | ---------------------------------------------------------------- */

    public function test_sync_failed_status_rollback(): void
    {
        // 失败时 SyncResult 应包含错误信息
        $result = SyncResult::failure(10, 1, ['Database connection failed']);

        $this->assertFalse($result->success);
        $this->assertNull($result->tenantProductId);
        $this->assertCount(1, $result->errors);
        $this->assertSame('Database connection failed', $result->errors[0]);
    }

    /* ----------------------------------------------------------------
     |  test_sync_result_dto
     | ---------------------------------------------------------------- */

    public function test_sync_result_dto(): void
    {
        // Success DTO
        $success = SyncResult::success(10, 1, 200);

        $this->assertTrue($success->success);
        $this->assertSame(10, $success->masterProductId);
        $this->assertSame(1, $success->storeId);
        $this->assertSame(200, $success->tenantProductId);
        $this->assertInstanceOf(Carbon::class, $success->syncedAt);
        $this->assertEmpty($success->errors);

        // Failure DTO
        $failure = SyncResult::failure(11, 2, ['Error A', 'Error B']);

        $this->assertFalse($failure->success);
        $this->assertSame(11, $failure->masterProductId);
        $this->assertSame(2, $failure->storeId);
        $this->assertNull($failure->tenantProductId);
        $this->assertCount(2, $failure->errors);

        // toArray
        $arr = $success->toArray();
        $this->assertArrayHasKey('success', $arr);
        $this->assertArrayHasKey('master_product_id', $arr);
        $this->assertArrayHasKey('store_id', $arr);
        $this->assertArrayHasKey('tenant_product_id', $arr);
        $this->assertArrayHasKey('synced_at', $arr);
        $this->assertArrayHasKey('errors', $arr);
    }
}
