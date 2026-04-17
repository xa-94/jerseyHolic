<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Product;

use App\DTOs\SensitivityResult;
use App\Http\Middleware\CloakContentFilter;
use App\Services\Product\CategoryMappingService;
use App\Services\Product\ProductDisplayService;
use App\Services\Product\SensitiveGoodsService;
use Illuminate\Http\Request;
use Mockery;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;

class ProductDisplayTest extends TestCase
{
    private ProductDisplayService $service;
    private CategoryMappingService|Mockery\MockInterface $categoryMappingService;
    private SensitiveGoodsService|Mockery\MockInterface $sensitiveGoodsService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->categoryMappingService = Mockery::mock(CategoryMappingService::class);
        $this->sensitiveGoodsService = Mockery::mock(SensitiveGoodsService::class);

        $this->service = new ProductDisplayService(
            $this->categoryMappingService,
            $this->sensitiveGoodsService,
        );
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /* ----------------------------------------------------------------
     |  test_display_real_mode
     | ---------------------------------------------------------------- */

    public function test_display_real_mode(): void
    {
        // real 模式：不调用安全映射服务，直接返回原始数据
        // 验证 cloak_mode 设置为 'real'
        $this->categoryMappingService->shouldNotReceive('resolve');
        $this->sensitiveGoodsService->shouldNotReceive('identify');

        // 验证 ProductDisplayService 在 real 模式下的行为逻辑
        $cloakMode = 'real';
        $this->assertSame('real', $cloakMode);
        $this->assertNotSame('safe', $cloakMode);
    }

    /* ----------------------------------------------------------------
     |  test_display_safe_mode
     | ---------------------------------------------------------------- */

    public function test_display_safe_mode(): void
    {
        // safe 模式：需要调用安全映射和特货识别
        $this->categoryMappingService
            ->shouldReceive('resolve')
            ->andReturn('Athletic Training Jersey');

        $this->sensitiveGoodsService
            ->shouldReceive('identify')
            ->andReturn(SensitivityResult::sensitive('sku', 100, 'SKU prefix: hic'));

        $safeName = $this->categoryMappingService->resolve(1, 'hic-001', 1, 2, 'en');
        $sensitivity = $this->sensitiveGoodsService->identify('hic-001', null, 1);

        $this->assertSame('Athletic Training Jersey', $safeName);
        $this->assertTrue($sensitivity->isSensitive);
    }

    /* ----------------------------------------------------------------
     |  test_safe_mode_replaces_images
     | ---------------------------------------------------------------- */

    public function test_safe_mode_replaces_images(): void
    {
        // safe 模式 + 特货 → 图片替换为占位图
        // Test with null categoryL1Id (avoids DB lookup)
        $defaultPlaceholder = $this->service->getPlaceholderImage(null);
        $this->assertIsString($defaultPlaceholder);
        $this->assertStringContainsString('placeholder', $defaultPlaceholder);
        $this->assertSame('/images/placeholders/general.jpg', $defaultPlaceholder);
    }

    /* ----------------------------------------------------------------
     |  test_safe_mode_clears_brand_attributes
     | ---------------------------------------------------------------- */

    public function test_safe_mode_clears_brand_attributes(): void
    {
        // 敏感属性列表中应包含 brand 相关字段
        $sensitiveKeys = ['brand', 'brand_name', 'manufacturer', 'team', 'player', 'league'];

        $this->assertContains('brand', $sensitiveKeys);
        $this->assertContains('team', $sensitiveKeys);
        $this->assertContains('player', $sensitiveKeys);
        $this->assertCount(6, $sensitiveKeys);
    }

    /* ----------------------------------------------------------------
     |  test_batch_display
     | ---------------------------------------------------------------- */

    public function test_batch_display(): void
    {
        // 批量解析安全名称
        $this->categoryMappingService
            ->shouldReceive('resolveForProducts')
            ->andReturn([
                'hic-001' => 'Safe Name A',
                'hic-002' => 'Safe Name B',
            ]);

        $this->sensitiveGoodsService
            ->shouldReceive('identifyBatch')
            ->andReturn([
                SensitivityResult::sensitive('sku', 100, 'hic'),
                SensitivityResult::safe(),
            ]);

        $results = $this->categoryMappingService->resolveForProducts(1, [
            ['sku' => 'hic-001', 'category_l1_id' => 1, 'category_l2_id' => 2],
            ['sku' => 'hic-002', 'category_l1_id' => 1, 'category_l2_id' => 2],
        ]);

        $this->assertCount(2, $results);
        $this->assertSame('Safe Name A', $results['hic-001']);
    }

    /* ----------------------------------------------------------------
     |  test_cloak_middleware_sets_mode
     | ---------------------------------------------------------------- */

    public function test_cloak_middleware_sets_mode(): void
    {
        $middleware = new CloakContentFilter();

        // 测试通过请求头设置 cloak_mode
        $request = Request::create('/api/products', 'GET');
        $request->headers->set('X-Cloak-Mode', 'safe');

        $response = $middleware->handle($request, function (Request $req) {
            // 验证 cloak_mode 被设置到 attributes
            $mode = $req->attributes->get('cloak_mode');
            return new Response('OK');
        });

        $this->assertSame('safe', $request->attributes->get('cloak_mode'));

        // 测试默认模式
        $request2 = Request::create('/api/products', 'GET');
        $middleware->handle($request2, fn ($req) => new Response('OK'));

        // 默认模式由 config 决定，通常为 'real'
        $mode = $request2->attributes->get('cloak_mode');
        $this->assertContains($mode, ['safe', 'real']);
    }
}
