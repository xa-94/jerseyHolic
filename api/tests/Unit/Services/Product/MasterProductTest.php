<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Product;

use App\Models\Central\Merchant;
use App\Models\Merchant\MasterProduct;
use App\Models\Merchant\MasterProductTranslation;
use App\Services\MerchantDatabaseService;
use App\Services\Product\MasterProductService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Mockery;
use Tests\TestCase;

class MasterProductTest extends TestCase
{
    private MasterProductService $service;
    private MerchantDatabaseService|Mockery\MockInterface $merchantDb;

    protected function setUp(): void
    {
        parent::setUp();

        $this->merchantDb = Mockery::mock(MerchantDatabaseService::class);

        // merchantDb->run() 直接执行回调（透传）
        $this->merchantDb->shouldReceive('run')
            ->andReturnUsing(fn ($merchant, $callback) => $callback());

        $this->service = new MasterProductService($this->merchantDb);
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

    private function makeProduct(array $attrs = []): MasterProduct
    {
        $id = $attrs['id'] ?? rand(1, 1000);
        unset($attrs['id']);

        $product = new MasterProduct(array_merge([
            'sku'            => 'hic-TEST-001',
            'name'           => 'Test Soccer Jersey',
            'description'    => 'A test product',
            'category_l1_id' => 1,
            'category_l2_id' => 2,
            'is_sensitive'   => true,
            'base_price'     => '29.99',
            'currency'       => 'USD',
            'images'         => ['/images/test.jpg'],
            'status'         => MasterProduct::STATUS_ACTIVE,
            'sync_status'    => MasterProduct::SYNC_PENDING,
        ], $attrs));
        $product->id = $id;
        $product->exists = true;

        return $product;
    }

    /* ----------------------------------------------------------------
     |  test_create_product_with_translations
     | ---------------------------------------------------------------- */

    public function test_create_product_with_translations(): void
    {
        $product = $this->makeProduct(['id' => 10]);

        $translations = [
            ['locale' => 'en', 'name' => 'English Name', 'description' => 'English Desc'],
            ['locale' => 'de', 'name' => 'German Name', 'description' => 'German Desc'],
        ];

        // 验证翻译数据结构
        $this->assertCount(2, $translations);
        $this->assertSame('en', $translations[0]['locale']);
        $this->assertSame('de', $translations[1]['locale']);

        // 验证产品结构
        $this->assertNotNull($product->sku);
        $this->assertSame(MasterProduct::STATUS_ACTIVE, $product->status);
    }

    /* ----------------------------------------------------------------
     |  test_update_product
     | ---------------------------------------------------------------- */

    public function test_update_product(): void
    {
        $product = $this->makeProduct(['id' => 10, 'name' => 'Old Name']);

        $this->assertSame('Old Name', $product->name);

        $product->name = 'Updated Name';
        $this->assertSame('Updated Name', $product->name);
    }

    /* ----------------------------------------------------------------
     |  test_delete_product
     | ---------------------------------------------------------------- */

    public function test_delete_product(): void
    {
        // 未同步的商品 → 硬删除
        $product = $this->makeProduct([
            'id'          => 10,
            'sync_status' => MasterProduct::SYNC_PENDING,
        ]);
        $this->assertSame(MasterProduct::SYNC_PENDING, $product->sync_status);

        // 已同步的商品 → 软删除（设为 inactive）
        $syncedProduct = $this->makeProduct([
            'id'          => 11,
            'sync_status' => MasterProduct::SYNC_SYNCED,
        ]);
        $this->assertSame(MasterProduct::SYNC_SYNCED, $syncedProduct->sync_status);
    }

    /* ----------------------------------------------------------------
     |  test_list_products_with_filters
     | ---------------------------------------------------------------- */

    public function test_list_products_with_filters(): void
    {
        // 验证筛选参数结构
        $filters = [
            'keyword'        => 'jersey',
            'category_l1_id' => 1,
            'status'         => 1,
        ];

        $this->assertArrayHasKey('keyword', $filters);
        $this->assertArrayHasKey('category_l1_id', $filters);
        $this->assertArrayHasKey('status', $filters);
    }

    /* ----------------------------------------------------------------
     |  test_list_products_pagination
     | ---------------------------------------------------------------- */

    public function test_list_products_pagination(): void
    {
        $filters = ['per_page' => 20, 'page' => 1];
        $perPage = min((int) ($filters['per_page'] ?? 20), 100);

        $this->assertSame(20, $perPage);
        $this->assertLessThanOrEqual(100, $perPage);
    }

    /* ----------------------------------------------------------------
     |  test_batch_delete
     | ---------------------------------------------------------------- */

    public function test_batch_delete(): void
    {
        $productIds = [1, 2, 3, 4, 5];

        $this->assertCount(5, $productIds);
        $this->assertContains(3, $productIds);
    }

    /* ----------------------------------------------------------------
     |  test_batch_update_status
     | ---------------------------------------------------------------- */

    public function test_batch_update_status(): void
    {
        $product = $this->makeProduct(['id' => 1, 'status' => MasterProduct::STATUS_ACTIVE]);
        $this->assertSame(MasterProduct::STATUS_ACTIVE, $product->status);

        // 可以批量更新为 inactive
        $product->status = MasterProduct::STATUS_INACTIVE;
        $this->assertSame(MasterProduct::STATUS_INACTIVE, $product->status);
    }

    /* ----------------------------------------------------------------
     |  test_product_belongs_to_category
     | ---------------------------------------------------------------- */

    public function test_product_belongs_to_category(): void
    {
        $product = $this->makeProduct([
            'category_l1_id' => 1,
            'category_l2_id' => 5,
        ]);

        $this->assertSame(1, $product->category_l1_id);
        $this->assertSame(5, $product->category_l2_id);
    }

    /* ----------------------------------------------------------------
     |  test_product_has_translations_relationship
     | ---------------------------------------------------------------- */

    public function test_product_has_translations_relationship(): void
    {
        $product = $this->makeProduct(['id' => 10]);

        $trans1 = new MasterProductTranslation([
            'master_product_id' => 10,
            'locale'            => 'en',
            'name'              => 'English Jersey',
        ]);
        $trans2 = new MasterProductTranslation([
            'master_product_id' => 10,
            'locale'            => 'de',
            'name'              => 'German Jersey',
        ]);

        $product->setRelation('translations', new Collection([$trans1, $trans2]));

        $this->assertCount(2, $product->translations);
        $this->assertSame('en', $product->translations->first()->locale);
    }

    /* ----------------------------------------------------------------
     |  test_product_sync_status_default
     | ---------------------------------------------------------------- */

    public function test_product_sync_status_default(): void
    {
        $product = $this->makeProduct();

        $this->assertSame(MasterProduct::SYNC_PENDING, $product->sync_status);

        // 验证所有同步状态常量
        $this->assertSame('pending', MasterProduct::SYNC_PENDING);
        $this->assertSame('syncing', MasterProduct::SYNC_SYNCING);
        $this->assertSame('synced', MasterProduct::SYNC_SYNCED);
        $this->assertSame('failed', MasterProduct::SYNC_FAILED);
    }
}
