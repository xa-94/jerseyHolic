<?php

namespace Tests\Unit\Services;

use App\Enums\MappingScenario;
use App\Enums\MappingType;
use App\Enums\SkuCategory;
use App\Models\Product;
use App\Models\ProductSafeMapping;
use App\Models\SafeProduct;
use App\Services\ProductMappingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class ProductMappingServiceTest extends TestCase
{
    use RefreshDatabase;

    private ProductMappingService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new ProductMappingService();
    }

    // === SKU Identification Tests ===

    public function test_identify_hic_sku(): void
    {
        $result = $this->service->identifySku('hic001');
        $this->assertEquals(SkuCategory::IMITATION, $result);
    }

    public function test_identify_hic_sku_case_insensitive(): void
    {
        $result = $this->service->identifySku('HIC-2024-RED');
        $this->assertEquals(SkuCategory::IMITATION, $result);
    }

    public function test_identify_wpz_sku(): void
    {
        $result = $this->service->identifySku('WPZ-100');
        $this->assertEquals(SkuCategory::FOREIGN_TRADE, $result);
    }

    public function test_identify_diy_sku(): void
    {
        $result = $this->service->identifySku('DIY2024');
        $this->assertEquals(SkuCategory::CUSTOM, $result);
    }

    public function test_identify_nbl_sku(): void
    {
        $result = $this->service->identifySku('NBL-001');
        $this->assertEquals(SkuCategory::NBL, $result);
    }

    public function test_identify_unknown_sku(): void
    {
        $result = $this->service->identifySku('ABC123');
        $this->assertEquals(SkuCategory::UNKNOWN, $result);
    }

    public function test_identify_empty_sku(): void
    {
        $result = $this->service->identifySku('');
        $this->assertEquals(SkuCategory::UNKNOWN, $result);
    }

    public function test_identify_short_sku(): void
    {
        $result = $this->service->identifySku('AB');
        $this->assertEquals(SkuCategory::UNKNOWN, $result);
    }

    // === Priority Chain Tests ===

    public function test_exact_mapping_has_highest_priority(): void
    {
        $product = Product::factory()->create(['sku' => 'hic-001', 'name' => 'Real Jersey Name']);
        $safeProduct = SafeProduct::factory()->create(['name' => 'Safe Mapped Name', 'description' => 'Safe description']);
        ProductSafeMapping::create([
            'product_id' => $product->id,
            'safe_product_id' => $safeProduct->id,
            'mapping_type' => MappingType::EXACT,
        ]);

        $result = $this->service->getSafeProductInfo($product, MappingScenario::PAYMENT);

        $this->assertEquals('Safe Mapped Name', $result['name']);
        $this->assertTrue($result['should_replace']);
    }

    public function test_prefix_fallback_when_no_exact_mapping(): void
    {
        $product = Product::factory()->create(['sku' => 'hic-002', 'name' => 'Real Jersey']);

        $result = $this->service->getSafeProductInfo($product, MappingScenario::PAYMENT);

        $this->assertEquals('Sports Jersey', $result['name']);
        $this->assertTrue($result['should_replace']);
    }

    public function test_default_fallback_when_no_prefix_match(): void
    {
        $product = Product::factory()->create(['sku' => 'XYZ-999', 'name' => 'Unknown Product']);

        $result = $this->service->getSafeProductInfo($product, MappingScenario::PAYMENT);

        $this->assertEquals('Sports Training Jersey', $result['name']);
        $this->assertTrue($result['should_replace']);
    }

    // === Scenario Rules Tests ===

    public function test_payment_scenario_uses_safe_name(): void
    {
        $product = Product::factory()->create(['sku' => 'hic-003', 'name' => 'Real Name']);
        $result = $this->service->getSafeProductInfo($product, MappingScenario::PAYMENT);
        $this->assertTrue($result['should_replace']);
        $this->assertNotEquals('Real Name', $result['name']);
    }

    public function test_logistics_scenario_uses_safe_name(): void
    {
        $product = Product::factory()->create(['sku' => 'hic-004', 'name' => 'Real Name']);
        $result = $this->service->getSafeProductInfo($product, MappingScenario::LOGISTICS);
        $this->assertTrue($result['should_replace']);
    }

    public function test_storefront_scenario_uses_real_name(): void
    {
        $product = Product::factory()->create(['sku' => 'hic-005', 'name' => 'My Real Jersey']);
        $result = $this->service->getSafeProductInfo($product, MappingScenario::STOREFRONT);
        $this->assertFalse($result['should_replace']);
        $this->assertEquals('My Real Jersey', $result['name']);
    }

    public function test_pixel_scenario_uses_real_name(): void
    {
        $product = Product::factory()->create(['sku' => 'hic-006', 'name' => 'Pixel Real Name']);
        $result = $this->service->getSafeProductInfo($product, MappingScenario::PIXEL);
        $this->assertFalse($result['should_replace']);
        $this->assertEquals('Pixel Real Name', $result['name']);
    }

    public function test_admin_scenario_returns_both(): void
    {
        $product = Product::factory()->create(['sku' => 'hic-007', 'name' => 'Admin Real Name']);
        $result = $this->service->getSafeProductInfo($product, MappingScenario::ADMIN);
        $this->assertArrayHasKey('real_name', $result);
        $this->assertEquals('Admin Real Name', $result['real_name']);
        $this->assertNotEquals('Admin Real Name', $result['name']);
    }

    // === WPZ Special Handling ===

    public function test_wpz_sku_uses_original_name(): void
    {
        $product = Product::factory()->create(['sku' => 'WPZ-100', 'name' => 'Foreign Trade Product']);
        $result = $this->service->getSafeProductInfo($product, MappingScenario::PAYMENT);
        $this->assertFalse($result['should_replace']);
        $this->assertEquals('Foreign Trade Product', $result['name']);
    }

    // === Edge Cases ===

    public function test_deleted_mapping_falls_back(): void
    {
        $product = Product::factory()->create(['sku' => 'hic-008', 'name' => 'Test Product']);
        $safeProduct = SafeProduct::factory()->create(['name' => 'Mapped Name']);
        $mapping = ProductSafeMapping::create([
            'product_id' => $product->id,
            'safe_product_id' => $safeProduct->id,
            'mapping_type' => MappingType::EXACT,
        ]);

        $this->service->deleteMapping($mapping->id);
        Cache::flush();

        $result = $this->service->getSafeProductInfo($product, MappingScenario::PAYMENT);
        $this->assertEquals('Sports Jersey', $result['name']);
    }

    public function test_price_never_replaced(): void
    {
        $product = Product::factory()->create(['sku' => 'hic-009', 'name' => 'Test', 'price' => 99.99]);
        $result = $this->service->getSafeProductInfo($product, MappingScenario::PAYMENT);
        $this->assertArrayNotHasKey('price', $result);
    }

    // === Cache Tests ===

    public function test_result_is_cached(): void
    {
        $product = Product::factory()->create(['sku' => 'hic-010', 'name' => 'Cache Test']);
        $this->service->getSafeProductInfo($product, MappingScenario::PAYMENT);
        $this->assertTrue(Cache::has('product_mapping:' . $product->id));
    }

    public function test_cache_cleared_on_mapping_change(): void
    {
        $product = Product::factory()->create(['sku' => 'hic-011']);
        $safeProduct = SafeProduct::factory()->create(['name' => 'Test']);

        $this->service->getSafeProductInfo($product, MappingScenario::PAYMENT);
        $this->assertTrue(Cache::has('product_mapping:' . $product->id));

        $this->service->createMapping($product->id, $safeProduct->id);
        $this->assertFalse(Cache::has('product_mapping:' . $product->id));
    }

    // === CRUD Tests ===

    public function test_create_safe_product(): void
    {
        $safeProduct = $this->service->createSafeProduct([
            'name' => 'Test Safe Product',
            'description' => 'Test description',
            'category' => 'general',
            'status' => 1,
        ]);
        $this->assertDatabaseHas('jh_safe_products', ['name' => 'Test Safe Product']);
    }

    public function test_create_and_delete_mapping(): void
    {
        $product = Product::factory()->create();
        $safeProduct = SafeProduct::factory()->create();

        $mapping = $this->service->createMapping($product->id, $safeProduct->id);
        $this->assertDatabaseHas('jh_product_safe_mapping', ['id' => $mapping->id]);

        $this->service->deleteMapping($mapping->id);
        $this->assertDatabaseMissing('jh_product_safe_mapping', ['id' => $mapping->id]);
    }
}
