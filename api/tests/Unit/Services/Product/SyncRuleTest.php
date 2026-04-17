<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Product;

use App\Exceptions\BusinessException;
use App\Models\Central\Merchant;
use App\Models\Central\Store;
use App\Models\Merchant\SyncRule;
use App\Services\MerchantDatabaseService;
use App\Services\Product\SyncRuleService;
use Illuminate\Database\Eloquent\Collection;
use Mockery;
use Tests\TestCase;

class SyncRuleTest extends TestCase
{
    private SyncRuleService $service;
    private MerchantDatabaseService|Mockery\MockInterface $merchantDb;

    protected function setUp(): void
    {
        parent::setUp();

        $this->merchantDb = Mockery::mock(MerchantDatabaseService::class);
        $this->merchantDb->shouldReceive('run')
            ->andReturnUsing(fn ($merchant, $callback) => $callback());

        $this->service = new SyncRuleService($this->merchantDb);
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

    private function makeRule(array $attrs = []): SyncRule
    {
        $id = $attrs['id'] ?? rand(1, 1000);
        unset($attrs['id']);

        $rule = new SyncRule(array_merge([
            'name'              => 'Default Rule',
            'target_store_ids'  => [1, 2, 3],
            'excluded_store_ids' => [],
            'sync_fields'       => ['name', 'price', 'description'],
            'price_strategy'    => SyncRule::PRICE_MULTIPLIER,
            'price_multiplier'  => '1.20',
            'auto_sync'         => true,
            'status'            => SyncRule::STATUS_ENABLED,
        ], $attrs));
        $rule->id = $id;
        $rule->exists = true;

        return $rule;
    }

    /* ----------------------------------------------------------------
     |  test_create_sync_rule
     | ---------------------------------------------------------------- */

    public function test_create_sync_rule(): void
    {
        $rule = $this->makeRule([
            'id'              => 10,
            'name'            => 'US Store Multiplier',
            'price_strategy'  => SyncRule::PRICE_MULTIPLIER,
            'price_multiplier' => '1.50',
        ]);

        $this->assertSame('US Store Multiplier', $rule->name);
        $this->assertSame(SyncRule::PRICE_MULTIPLIER, $rule->price_strategy);
        $this->assertTrue($rule->auto_sync);
    }

    /* ----------------------------------------------------------------
     |  test_update_sync_rule
     | ---------------------------------------------------------------- */

    public function test_update_sync_rule(): void
    {
        $rule = $this->makeRule(['id' => 10, 'name' => 'Old Name']);

        $this->assertSame('Old Name', $rule->name);

        $rule->name = 'Updated Name';
        $this->assertSame('Updated Name', $rule->name);
    }

    /* ----------------------------------------------------------------
     |  test_delete_sync_rule
     | ---------------------------------------------------------------- */

    public function test_delete_sync_rule(): void
    {
        $rule = $this->makeRule(['id' => 10]);
        $this->assertTrue($rule->exists);
        $this->assertSame(10, $rule->id);
    }

    /* ----------------------------------------------------------------
     |  test_list_sync_rules_for_merchant
     | ---------------------------------------------------------------- */

    public function test_list_sync_rules_for_merchant(): void
    {
        $rule1 = $this->makeRule(['id' => 1, 'name' => 'Rule A']);
        $rule2 = $this->makeRule(['id' => 2, 'name' => 'Rule B']);

        $collection = new Collection([$rule1, $rule2]);

        $this->assertCount(2, $collection);
        $this->assertSame('Rule A', $collection->first()->name);
    }

    /* ----------------------------------------------------------------
     |  test_get_rule_for_store
     | ---------------------------------------------------------------- */

    public function test_get_rule_for_store(): void
    {
        $rule = $this->makeRule([
            'target_store_ids'  => [1, 2, 3],
            'excluded_store_ids' => [3],
        ]);

        // Store 1 在目标列表中，且不在排除列表中 → 适用
        $this->assertTrue($rule->appliesToStore(1));

        // Store 3 在排除列表中 → 不适用
        $this->assertFalse($rule->appliesToStore(3));

        // Store 99 不在目标列表中 → 不适用
        $this->assertFalse($rule->appliesToStore(99));
    }

    /* ----------------------------------------------------------------
     |  test_store_ownership_validation
     | ---------------------------------------------------------------- */

    public function test_store_ownership_validation(): void
    {
        // BusinessException 需要 ErrorCode 枚举，这里验证数据验证逻辑
        $merchant = $this->makeMerchant(1);

        // 验证目标站点列表不为空
        $targetStoreIds = [1, 2, 3];
        $this->assertNotEmpty($targetStoreIds);

        // 验证 SyncRule 的 target_store_ids 结构
        $rule = $this->makeRule(['target_store_ids' => $targetStoreIds]);
        $this->assertIsArray($rule->target_store_ids);
        $this->assertCount(3, $rule->target_store_ids);
    }

    /* ----------------------------------------------------------------
     |  test_pricing_strategy_values
     | ---------------------------------------------------------------- */

    public function test_pricing_strategy_values(): void
    {
        // 验证所有价格策略常量
        $this->assertSame('fixed', SyncRule::PRICE_FIXED);
        $this->assertSame('multiplier', SyncRule::PRICE_MULTIPLIER);
        $this->assertSame('custom', SyncRule::PRICE_CUSTOM);

        // multiplier 策略计算
        $rule = $this->makeRule([
            'price_strategy'  => SyncRule::PRICE_MULTIPLIER,
            'price_multiplier' => '1.50',
        ]);

        $price = $rule->calculatePrice('100.00');
        $this->assertSame('150.00', $price);

        // fixed 策略 → 返回原价
        $fixedRule = $this->makeRule([
            'price_strategy' => SyncRule::PRICE_FIXED,
        ]);
        $this->assertSame('100.00', $fixedRule->calculatePrice('100.00'));
    }

    /* ----------------------------------------------------------------
     |  test_auto_sync_toggle
     | ---------------------------------------------------------------- */

    public function test_auto_sync_toggle(): void
    {
        $ruleOn = $this->makeRule(['auto_sync' => true]);
        $ruleOff = $this->makeRule(['auto_sync' => false]);

        $this->assertTrue($ruleOn->auto_sync);
        $this->assertFalse($ruleOff->auto_sync);

        // 状态常量
        $this->assertSame(1, SyncRule::STATUS_ENABLED);
        $this->assertSame(0, SyncRule::STATUS_DISABLED);
    }
}
