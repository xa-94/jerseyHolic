<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Product;

use App\Models\Central\CategorySafeName;
use App\Services\Product\CategoryMappingService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Mockery;
use Tests\TestCase;

class CategoryMappingTest extends TestCase
{
    private CategoryMappingService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new CategoryMappingService();
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /* ----------------------------------------------------------------
     |  Helper: 创建 Mock CategorySafeName
     | ---------------------------------------------------------------- */

    private function makeSafeName(array $attrs = []): CategorySafeName
    {
        $id = $attrs['id'] ?? rand(1, 1000);
        unset($attrs['id']);

        $item = new CategorySafeName(array_merge([
            'category_l1_id' => null,
            'category_l2_id' => null,
            'sku_prefix'     => null,
            'store_id'       => null,
            'safe_name_en'   => 'Athletic Training Jersey',
            'safe_name_de'   => 'Sportliches Trainingstrikot',
            'weight'         => 10,
            'status'         => CategorySafeName::STATUS_ACTIVE,
        ], $attrs));
        $item->id = $id;
        $item->exists = true;

        return $item;
    }

    /* ----------------------------------------------------------------
     |  test_resolve_level1_store_override
     | ---------------------------------------------------------------- */

    public function test_resolve_level1_store_override(): void
    {
        $candidate = $this->makeSafeName([
            'store_id'       => 42,
            'category_l1_id' => 1,
            'safe_name_en'   => 'Store Override Jersey',
            'weight'         => 100,
        ]);

        // Mock Cache::remember to return store-level candidates
        Cache::shouldReceive('remember')
            ->once()
            ->andReturn(new Collection([$candidate]));

        $result = $this->service->resolve(
            storeId: 42,
            sku: 'hic-001',
            categoryL1Id: 1,
            categoryL2Id: 2,
            locale: 'en',
        );

        $this->assertSame('Store Override Jersey', $result);
    }

    /* ----------------------------------------------------------------
     |  test_resolve_level2_exact_sku_match
     | ---------------------------------------------------------------- */

    public function test_resolve_level2_exact_sku_match(): void
    {
        $skuCandidate = $this->makeSafeName([
            'sku_prefix'   => 'hic-EXACT-001',
            'safe_name_en' => 'Exact SKU Match Jersey',
            'weight'       => 50,
        ]);

        // Level 1: 站点覆盖 — 无候选
        // Level 2: 精确 SKU — 有候选
        Cache::shouldReceive('remember')
            ->once()
            ->andReturn(new Collection([])); // L1 no hit
        Cache::shouldReceive('remember')
            ->once()
            ->andReturn(new Collection([$skuCandidate])); // L2 hit

        $result = $this->service->resolve(
            storeId: 1,
            sku: 'hic-EXACT-001',
            categoryL1Id: 1,
            categoryL2Id: null,
        );

        $this->assertSame('Exact SKU Match Jersey', $result);
    }

    /* ----------------------------------------------------------------
     |  test_resolve_level3_sku_prefix_match
     | ---------------------------------------------------------------- */

    public function test_resolve_level3_sku_prefix_match(): void
    {
        $prefixCandidate = $this->makeSafeName([
            'sku_prefix'   => 'hic',
            'safe_name_en' => 'Prefix Match Jersey',
            'weight'       => 30,
        ]);

        // L1 miss, L2 miss, L3 hit
        Cache::shouldReceive('remember')
            ->times(3)
            ->andReturn(
                new Collection([]), // L1 store override
                new Collection([]), // L2 exact SKU
                new Collection([$prefixCandidate]), // L3 prefix
            );

        $result = $this->service->resolve(
            storeId: 1,
            sku: 'hic-999',
            categoryL1Id: 1,
            categoryL2Id: null,
        );

        $this->assertSame('Prefix Match Jersey', $result);
    }

    /* ----------------------------------------------------------------
     |  test_resolve_level4_category_level
     | ---------------------------------------------------------------- */

    public function test_resolve_level4_category_level(): void
    {
        $catCandidate = $this->makeSafeName([
            'category_l1_id' => 1,
            'category_l2_id' => 2,
            'safe_name_en'   => 'Category Level Jersey',
            'weight'         => 20,
        ]);

        // L1 miss → L4 hit (no SKU means L2/L3 skipped)
        Cache::shouldReceive('remember')
            ->once()
            ->andReturn(new Collection([])); // L1
        Cache::shouldReceive('remember')
            ->once()
            ->andReturn(new Collection([$catCandidate])); // L4

        $result = $this->service->resolve(
            storeId: 1,
            sku: null,
            categoryL1Id: 1,
            categoryL2Id: 2,
        );

        $this->assertSame('Category Level Jersey', $result);
    }

    /* ----------------------------------------------------------------
     |  test_resolve_level5_fallback
     | ---------------------------------------------------------------- */

    public function test_resolve_level5_fallback(): void
    {
        // All levels miss → fallback "General Merchandise"
        Cache::shouldReceive('remember')->andReturn(new Collection([]));

        $result = $this->service->resolve(
            storeId: 1,
            sku: null,
            categoryL1Id: null,
            categoryL2Id: null,
        );

        $this->assertSame('General Merchandise', $result);
    }

    /* ----------------------------------------------------------------
     |  test_priority_order_store_over_sku
     | ---------------------------------------------------------------- */

    public function test_priority_order_store_over_sku(): void
    {
        $storeCandidate = $this->makeSafeName([
            'store_id'       => 42,
            'category_l1_id' => 1,
            'safe_name_en'   => 'Store Level Name',
            'weight'         => 100,
        ]);

        // L1 hit immediately → never reaches L2
        Cache::shouldReceive('remember')
            ->once()
            ->andReturn(new Collection([$storeCandidate]));

        $result = $this->service->resolve(42, 'hic-001', 1, 2);

        $this->assertSame('Store Level Name', $result);
    }

    /* ----------------------------------------------------------------
     |  test_weighted_random_selection
     | ---------------------------------------------------------------- */

    public function test_weighted_random_selection(): void
    {
        $a = $this->makeSafeName(['weight' => 90, 'safe_name_en' => 'Name A']);
        $b = $this->makeSafeName(['weight' => 10, 'safe_name_en' => 'Name B']);

        Cache::shouldReceive('remember')
            ->once()
            ->andReturn(new Collection([$a, $b]));

        $result = $this->service->resolve(42, null, 1, null);

        // 结果应是 "Name A" 或 "Name B"（概率分布，至少属于候选列表）
        $this->assertContains($result, ['Name A', 'Name B']);
    }

    /* ----------------------------------------------------------------
     |  test_resolve_for_products_batch
     | ---------------------------------------------------------------- */

    public function test_resolve_for_products_batch(): void
    {
        $candidate = $this->makeSafeName([
            'safe_name_en' => 'Batch Safe Name',
            'weight'       => 100,
        ]);

        // 所有 Cache::remember 调用返回同一候选列表
        Cache::shouldReceive('remember')->andReturn(new Collection([$candidate]));

        $products = [
            ['sku' => 'hic-001', 'category_l1_id' => 1, 'category_l2_id' => 2],
            ['sku' => 'hic-002', 'category_l1_id' => 1, 'category_l2_id' => 2],
            ['sku' => 'WPZ-003', 'category_l1_id' => 1, 'category_l2_id' => 3],
        ];

        $results = $this->service->resolveForProducts(1, $products);

        $this->assertIsArray($results);
        $this->assertArrayHasKey('hic-001', $results);
        $this->assertArrayHasKey('hic-002', $results);
        $this->assertArrayHasKey('WPZ-003', $results);
    }

    /* ----------------------------------------------------------------
     |  test_cache_hit_and_miss
     | ---------------------------------------------------------------- */

    public function test_cache_hit_and_miss(): void
    {
        $candidate = $this->makeSafeName([
            'store_id'       => 1,
            'category_l1_id' => 1,
            'safe_name_en'   => 'Cached Name',
            'weight'         => 100,
        ]);

        // Cache::remember should be called — simulating cache hit by returning immediately
        Cache::shouldReceive('remember')
            ->once()
            ->andReturn(new Collection([$candidate]));

        $result1 = $this->service->resolve(1, null, 1, null);

        $this->assertSame('Cached Name', $result1);
    }

    /* ----------------------------------------------------------------
     |  test_clear_cache
     | ---------------------------------------------------------------- */

    public function test_clear_cache(): void
    {
        // clearCache 会调用 Cache::getStore()->getRedis()->scan(...)
        // 我们验证不抛异常即可（Redis 不可用时走 catch）
        Cache::shouldReceive('getStore')
            ->andReturnSelf();
        Cache::shouldReceive('getRedis')
            ->andReturnSelf();
        Cache::shouldReceive('scan')
            ->andReturn([0, []]);

        $this->service->clearCache(42);
        $this->assertTrue(true); // 无异常即通过
    }
}
