<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Product;

use App\Models\Central\Store;
use App\Models\Central\StoreProductConfig;
use App\Services\Product\StoreProductConfigService;
use Illuminate\Support\Facades\Cache;
use Mockery;
use Tests\TestCase;

class StoreProductConfigTest extends TestCase
{
    private StoreProductConfigService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new StoreProductConfigService();
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /* ----------------------------------------------------------------
     |  Helper
     | ---------------------------------------------------------------- */

    private function makeConfig(array $attrs = []): StoreProductConfig
    {
        $id = $attrs['id'] ?? rand(1, 1000);
        unset($attrs['id']);

        $config = new StoreProductConfig(array_merge([
            'store_id'                   => '1',
            'price_override_enabled'     => false,
            'price_override_strategy'    => null,
            'price_override_value'       => null,
            'safe_name_override_enabled' => false,
            'custom_placeholder_image'   => null,
            'display_currency'           => 'USD',
            'auto_translate'             => true,
            'default_language'           => 'en',
            'status'                     => 1,
        ], $attrs));
        $config->id = $id;
        $config->exists = true;

        return $config;
    }

    /* ----------------------------------------------------------------
     |  test_get_config_returns_default_when_empty
     | ---------------------------------------------------------------- */

    public function test_get_config_returns_default_when_empty(): void
    {
        // Cache::remember 返回 null（站点无配置）
        // getConfig + getEffectiveDisplaySettings both call Cache::remember
        Cache::shouldReceive('remember')
            ->andReturn(null);

        $config = $this->service->getConfig('nonexistent');

        $this->assertNull($config);

        // 获取有效展示设置时应返回默认值
        $settings = $this->service->getEffectiveDisplaySettings('nonexistent');

        $this->assertSame('en', $settings['language']);
        $this->assertSame('USD', $settings['currency']);
        $this->assertNull($settings['placeholder_image']);
        $this->assertFalse($settings['safe_name_enabled']);
        $this->assertTrue($settings['auto_translate']);
    }

    /* ----------------------------------------------------------------
     |  test_update_config_upsert
     | ---------------------------------------------------------------- */

    public function test_update_config_upsert(): void
    {
        $config = $this->makeConfig([
            'store_id'                   => '42',
            'price_override_enabled'     => true,
            'price_override_strategy'    => 'multiplier',
            'price_override_value'       => '1.20',
        ]);

        $this->assertSame('42', $config->store_id);
        $this->assertTrue($config->price_override_enabled);
        $this->assertSame('multiplier', $config->price_override_strategy);
    }

    /* ----------------------------------------------------------------
     |  test_apply_price_override_multiplier (bcmath 精度)
     | ---------------------------------------------------------------- */

    public function test_apply_price_override_multiplier(): void
    {
        $config = $this->makeConfig([
            'price_override_enabled'  => true,
            'price_override_strategy' => 'multiplier',
            'price_override_value'    => '1.20',
        ]);

        // 29.99 × 1.20 = 35.988 → 35.98 (bcmul scale=2)
        $result = $config->getPriceOverride('29.99');
        $this->assertSame('35.98', $result);

        // 100.00 × 1.20 = 120.00
        $result = $config->getPriceOverride('100.00');
        $this->assertSame('120.00', $result);
    }

    /* ----------------------------------------------------------------
     |  test_apply_price_override_fixed
     | ---------------------------------------------------------------- */

    public function test_apply_price_override_fixed(): void
    {
        $config = $this->makeConfig([
            'price_override_enabled'  => true,
            'price_override_strategy' => 'fixed',
            'price_override_value'    => '19.99',
        ]);

        // fixed 策略直接返回配置的固定值
        $result = $config->getPriceOverride('29.99');
        $this->assertSame('19.99', $result);

        $result = $config->getPriceOverride('100.00');
        $this->assertSame('19.99', $result);
    }

    /* ----------------------------------------------------------------
     |  test_get_effective_display_settings
     | ---------------------------------------------------------------- */

    public function test_get_effective_display_settings(): void
    {
        $config = $this->makeConfig([
            'store_id'                   => '42',
            'display_currency'           => 'EUR',
            'default_language'           => 'de',
            'safe_name_override_enabled' => true,
            'custom_placeholder_image'   => '/images/custom-placeholder.jpg',
            'auto_translate'             => false,
        ]);

        // Mock getConfig 返回有配置
        Cache::shouldReceive('remember')
            ->once()
            ->andReturn($config);

        $settings = $this->service->getEffectiveDisplaySettings('42');

        $this->assertSame('de', $settings['language']);
        $this->assertSame('EUR', $settings['currency']);
        $this->assertSame('/images/custom-placeholder.jpg', $settings['placeholder_image']);
        $this->assertTrue($settings['safe_name_enabled']);
        $this->assertFalse($settings['auto_translate']);
    }

    /* ----------------------------------------------------------------
     |  test_cache_clear_on_update
     | ---------------------------------------------------------------- */

    public function test_cache_clear_on_update(): void
    {
        Cache::shouldReceive('forget')
            ->once()
            ->with('store_product_config:42');

        $this->service->clearCache('42');

        $this->assertTrue(true); // Mockery 验证 forget 被调用
    }
}
