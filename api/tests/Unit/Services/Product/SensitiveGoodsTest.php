<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Product;

use App\DTOs\OrderSensitivityResult;
use App\DTOs\SensitivityResult;
use App\Models\Central\ProductCategoryL1;
use App\Models\Central\SensitiveBrand;
use App\Services\Product\SensitiveGoodsService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Mockery;
use Tests\TestCase;

class SensitiveGoodsTest extends TestCase
{
    private SensitiveGoodsService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new SensitiveGoodsService();
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /* ----------------------------------------------------------------
     |  Helper
     | ---------------------------------------------------------------- */

    private function makeBrand(array $attrs = []): SensitiveBrand
    {
        $id = $attrs['id'] ?? rand(1, 1000);
        unset($attrs['id']);

        $brand = new SensitiveBrand(array_merge([
            'brand_name'     => 'Nike',
            'brand_aliases'  => ['NIKE', 'NK'],
            'category_l1_id' => null,
            'risk_level'     => SensitiveBrand::RISK_HIGH,
            'reason'         => 'Trademark risk',
            'status'         => 1, // SensitiveBrand::STATUS_ACTIVE
        ], $attrs));
        $brand->id = $id;
        $brand->exists = true;

        return $brand;
    }

    private function makeCategory(array $attrs = []): ProductCategoryL1
    {
        $id = $attrs['id'] ?? rand(1, 1000);
        unset($attrs['id']);

        $cat = new ProductCategoryL1(array_merge([
            'code'            => 'SOCCER',
            'name'            => ['en' => 'Soccer'],
            'is_sensitive'    => true,
            'sensitive_ratio' => '85.00',
            'sort_order'      => 10,
            'status'          => 1, // ProductCategoryL1::STATUS_ACTIVE
        ], $attrs));
        $cat->id = $id;
        $cat->exists = true;

        return $cat;
    }

    /* ----------------------------------------------------------------
     |  test_level1_sku_prefix_detection
     | ---------------------------------------------------------------- */

    public function test_level1_sku_prefix_detection(): void
    {
        // SKU 以 'hic' 开头 → Level 1 命中，confidence = 100%
        $result = $this->service->identify('hic-ABC-001');

        $this->assertTrue($result->isSensitive);
        $this->assertSame('sku', $result->level);
        $this->assertSame(100, $result->confidence);
        $this->assertStringContainsString('hic', $result->matchedRule);
    }

    /* ----------------------------------------------------------------
     |  test_level2_brand_blacklist_match
     | ---------------------------------------------------------------- */

    public function test_level2_brand_blacklist_match(): void
    {
        $nikeBrand = $this->makeBrand([
            'brand_name'    => 'Nike',
            'brand_aliases' => ['NIKE', 'NK'],
            'risk_level'    => 'high',
        ]);

        // Mock Cache::remember 返回品牌黑名单
        Cache::shouldReceive('remember')
            ->andReturn(new Collection([$nikeBrand]));

        // SKU 不匹配前缀（使用非敏感前缀）, 但品牌命中
        $result = $this->service->identify('SAFE-001', 'Nike');

        $this->assertTrue($result->isSensitive);
        $this->assertSame('brand', $result->level);
        $this->assertSame(90, $result->confidence);
    }

    /* ----------------------------------------------------------------
     |  test_level3_category_sensitivity
     | ---------------------------------------------------------------- */

    public function test_level3_category_sensitivity(): void
    {
        $category = $this->makeCategory([
            'id'              => 1,
            'is_sensitive'    => true,
            'sensitive_ratio' => '85.00',
        ]);

        // Mock: no brand hit, but category is sensitive
        Cache::shouldReceive('remember')
            ->andReturn(new Collection([])); // 无品牌黑名单

        // Pre-populate category cache via reflection instead of alias mock
        $ref = new \ReflectionProperty($this->service, 'categoryCache');
        $ref->setAccessible(true);
        $ref->setValue($this->service, [1 => $category]);

        $result = $this->service->identify('SAFE-001', null, 1);

        $this->assertTrue($result->isSensitive);
        $this->assertSame('category', $result->level);
        $this->assertSame(85, $result->confidence);
    }

    /* ----------------------------------------------------------------
     |  test_non_sensitive_product
     | ---------------------------------------------------------------- */

    public function test_non_sensitive_product(): void
    {
        // 非敏感前缀 SKU, 无品牌, 无品类 → safe
        $result = $this->service->identify('NORMAL-001');

        $this->assertFalse($result->isSensitive);
        $this->assertSame('none', $result->level);
        $this->assertSame(0, $result->confidence);
        $this->assertNull($result->matchedRule);
    }

    /* ----------------------------------------------------------------
     |  test_analyze_order_mixed (BR-MIX-001)
     | ---------------------------------------------------------------- */

    public function test_analyze_order_mixed(): void
    {
        // Mock Cache to avoid DB hit on sensitive_brands
        Cache::shouldReceive('remember')
            ->andReturn(new Collection([]));

        // 混合订单：1 个特货 + 1 个普货
        $orderItems = [
            ['sku' => 'hic-001', 'brand' => null, 'category_l1_id' => null],
            ['sku' => 'NORMAL-001', 'brand' => null, 'category_l1_id' => null],
        ];

        $result = $this->service->analyzeOrder($orderItems);

        $this->assertInstanceOf(OrderSensitivityResult::class, $result);
        $this->assertTrue($result->hasSensitiveItems);
        $this->assertTrue($result->requireSafeMapping); // BR-MIX-002
        $this->assertSame('all_safe', $result->overallStrategy); // BR-MIX-001
    }

    /* ----------------------------------------------------------------
     |  test_analyze_order_all_normal
     | ---------------------------------------------------------------- */

    public function test_analyze_order_all_normal(): void
    {
        // Mock Cache to avoid DB hit on sensitive_brands
        Cache::shouldReceive('remember')
            ->andReturn(new Collection([]));

        $orderItems = [
            ['sku' => 'NORMAL-001', 'brand' => null, 'category_l1_id' => null],
            ['sku' => 'NORMAL-002', 'brand' => null, 'category_l1_id' => null],
        ];

        $result = $this->service->analyzeOrder($orderItems);

        $this->assertFalse($result->hasSensitiveItems);
        $this->assertFalse($result->requireSafeMapping);
        $this->assertSame('normal', $result->overallStrategy);
    }

    /* ----------------------------------------------------------------
     |  test_identify_batch
     | ---------------------------------------------------------------- */

    public function test_identify_batch(): void
    {
        // Mock Cache to avoid DB hit on sensitive_brands
        Cache::shouldReceive('remember')
            ->andReturn(new Collection([]));

        $items = [
            ['sku' => 'hic-001', 'brand' => null, 'category_l1_id' => null],
            ['sku' => 'NORMAL-001', 'brand' => null, 'category_l1_id' => null],
            ['sku' => 'NBA-100', 'brand' => null, 'category_l1_id' => null],
        ];

        $results = $this->service->identifyBatch($items);

        $this->assertCount(3, $results);
        $this->assertTrue($results[0]->isSensitive);  // hic prefix
        $this->assertFalse($results[1]->isSensitive); // NORMAL - not sensitive
        $this->assertTrue($results[2]->isSensitive);  // NBA prefix
    }

    /* ----------------------------------------------------------------
     |  test_brand_alias_matching
     | ---------------------------------------------------------------- */

    public function test_brand_alias_matching(): void
    {
        $brand = $this->makeBrand([
            'brand_name'    => 'Adidas',
            'brand_aliases' => ['adidas', 'ADS', 'Adi'],
        ]);

        // SensitiveBrand::matchesBrand 应该匹配别名
        $this->assertTrue($brand->matchesBrand('adidas'));
        $this->assertTrue($brand->matchesBrand('ADS'));
        $this->assertTrue($brand->matchesBrand('Adi'));
        $this->assertTrue($brand->matchesBrand('Adidas Official')); // contains match
        $this->assertFalse($brand->matchesBrand('Puma'));
    }
}
