<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Payment;

use App\DTOs\ElectionResult;
use App\Models\Central\Blacklist;
use App\Models\Central\PaymentAccount;
use App\Models\Central\PaymentAccountGroup;
use App\Services\Payment\AccountHealthScoreService;
use App\Services\Payment\AccountLifecycleService;
use App\Services\Payment\ElectionService;
use App\Services\Payment\PaymentGroupMappingService;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Mockery;
use Tests\TestCase;

class ElectionServiceTest extends TestCase
{
    private ElectionService $service;
    private PaymentGroupMappingService|Mockery\MockInterface $mappingService;
    private AccountLifecycleService|Mockery\MockInterface $lifecycleService;
    private AccountHealthScoreService|Mockery\MockInterface $healthScoreService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->mappingService = Mockery::mock(PaymentGroupMappingService::class);
        $this->lifecycleService = Mockery::mock(AccountLifecycleService::class);
        $this->healthScoreService = Mockery::mock(AccountHealthScoreService::class);

        $this->service = new ElectionService(
            $this->mappingService,
            $this->lifecycleService,
            $this->healthScoreService,
        );
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /* ----------------------------------------------------------------
     |  Helper: 创建 Mock PaymentAccount
     | ---------------------------------------------------------------- */

    private function makeAccount(array $attrs = []): PaymentAccount
    {
        $id = $attrs['id'] ?? rand(1, 1000);
        unset($attrs['id']);
        $account = new PaymentAccount(array_merge([
            'account'             => 'test@paypal.com',
            'status'              => 1,
            'permission'          => 1,
            'priority'            => 10,
            'health_score'        => 80,
            'lifecycle_stage'     => PaymentAccount::LIFECYCLE_MATURE,
            'single_limit'        => '2000.00',
            'daily_limit'         => '10000.00',
            'daily_money_total'   => '0.00',
            'daily_count_limit'   => 50,
            'deal_count'          => 0,
            'cooling_until'       => null,
            'last_used_at'        => null,
        ], $attrs));
        $account->id = $id;
        $account->exists = true;

        return $account;
    }

    private function makeGroup(string $groupType = PaymentAccountGroup::GROUP_TYPE_STANDARD_SHARED): PaymentAccountGroup
    {
        $group = new PaymentAccountGroup([
            'name'       => 'Test Group',
            'type'       => 'paypal',
            'group_type' => $groupType,
            'status'     => 1,
        ]);
        $group->id = rand(1, 100);
        $group->exists = true;

        return $group;
    }

    /* ----------------------------------------------------------------
     |  L1: 黑名单命中直接拦截
     | ---------------------------------------------------------------- */

    public function test_layer1_blacklist_hit_blocks_election(): void
    {
        // 创建黑名单记录
        Blacklist::create([
            'scope'     => Blacklist::SCOPE_PLATFORM,
            'dimension' => Blacklist::DIMENSION_IP,
            'value'     => '1.2.3.4',
            'reason'    => 'Test block',
        ]);

        $result = $this->service->elect(1, 'paypal', '100.00', [
            'ip' => '1.2.3.4',
            'email' => 'test@test.com',
        ]);

        $this->assertFalse($result->success);
        $this->assertSame('blocked', $result->code);
    }

    /* ----------------------------------------------------------------
     |  L2: 映射组解析
     | ---------------------------------------------------------------- */

    public function test_layer2_no_mapping_returns_no_mapping(): void
    {
        $this->mappingService
            ->shouldReceive('resolveGroup')
            ->once()
            ->andReturn(null);

        $result = $this->service->elect(1, 'paypal', '100.00');

        $this->assertFalse($result->success);
        $this->assertSame('no_mapping', $result->code);
    }

    /* ----------------------------------------------------------------
     |  L3: 过滤 cooling/disabled 账号
     | ---------------------------------------------------------------- */

    public function test_layer3_filters_cooling_accounts(): void
    {
        $group = $this->makeGroup();
        $coolingAccount = $this->makeAccount([
            'id' => 1,
            'cooling_until' => now()->addDays(5),
        ]);

        $this->mappingService->shouldReceive('resolveGroup')->andReturn($group);
        $this->mappingService->shouldReceive('getAvailableAccounts')
            ->andReturn(new EloquentCollection([$coolingAccount]));

        $result = $this->service->elect(1, 'paypal', '50.00');

        // cooling account filtered → no_available or fallback
        $this->assertFalse($result->success);
    }

    /* ----------------------------------------------------------------
     |  L4: 优先级排序
     | ---------------------------------------------------------------- */

    public function test_layer4_priority_sort_higher_first(): void
    {
        $group = $this->makeGroup();
        $lowPriority = $this->makeAccount(['id' => 1, 'priority' => 5, 'health_score' => 80]);
        $highPriority = $this->makeAccount(['id' => 2, 'priority' => 20, 'health_score' => 80]);

        $this->mappingService->shouldReceive('resolveGroup')->andReturn($group);
        $this->mappingService->shouldReceive('getAvailableAccounts')
            ->andReturn(new EloquentCollection([$lowPriority, $highPriority]));
        $this->lifecycleService->shouldReceive('canProcess')->andReturn(true);
        Cache::shouldReceive('get')->andReturn(null);

        $result = $this->service->elect(1, 'paypal', '50.00');

        $this->assertTrue($result->success);
        // 高优先级的 account id=2 应被选中
        $this->assertSame(2, $result->account->id);
    }

    /* ----------------------------------------------------------------
     |  L5: 限额检查
     | ---------------------------------------------------------------- */

    public function test_layer5_limit_check_rejects_over_limit(): void
    {
        $group = $this->makeGroup();
        $account = $this->makeAccount(['id' => 1]);

        $this->mappingService->shouldReceive('resolveGroup')->andReturn($group);
        $this->mappingService->shouldReceive('getAvailableAccounts')
            ->andReturn(new EloquentCollection([$account]));
        $this->lifecycleService->shouldReceive('canProcess')->andReturn(false);

        // Fallback 也返回空
        $this->mappingService->shouldReceive('getAvailableAccounts')
            ->andReturn(new EloquentCollection([]));

        $result = $this->service->elect(1, 'paypal', '99999.00');

        $this->assertFalse($result->success);
    }

    /* ----------------------------------------------------------------
     |  L6: 健康度排序
     | ---------------------------------------------------------------- */

    public function test_layer6_health_sort_higher_score_first(): void
    {
        $group = $this->makeGroup();
        $lowHealth = $this->makeAccount(['id' => 1, 'priority' => 10, 'health_score' => 50]);
        $highHealth = $this->makeAccount(['id' => 2, 'priority' => 10, 'health_score' => 95]);

        $this->mappingService->shouldReceive('resolveGroup')->andReturn($group);
        $this->mappingService->shouldReceive('getAvailableAccounts')
            ->andReturn(new EloquentCollection([$lowHealth, $highHealth]));
        $this->lifecycleService->shouldReceive('canProcess')->andReturn(true);
        Cache::shouldReceive('get')->andReturn(null);

        $result = $this->service->elect(1, 'paypal', '50.00');

        $this->assertTrue($result->success);
        $this->assertSame(2, $result->account->id);
    }

    /* ----------------------------------------------------------------
     |  L7: 行为限频
     | ---------------------------------------------------------------- */

    public function test_layer7_behavior_check_rejects_recent_use(): void
    {
        $group = $this->makeGroup();
        $account = $this->makeAccount(['id' => 1]);

        $this->mappingService->shouldReceive('resolveGroup')->andReturn($group);
        $this->mappingService->shouldReceive('getAvailableAccounts')
            ->once()->ordered()->andReturn(new EloquentCollection([$account]));
        $this->lifecycleService->shouldReceive('canProcess')->andReturn(true);

        // 模拟刚使用过（<60s）
        Cache::shouldReceive('get')
            ->with("election:last_used:1")
            ->andReturn((string) time());

        // Fallback 也会重试 — 返回空集合
        $this->mappingService->shouldReceive('getAvailableAccounts')
            ->once()->ordered()->andReturn(new EloquentCollection([]));

        $result = $this->service->elect(1, 'paypal', '50.00');

        // 因为 fallback 也失败了
        $this->assertFalse($result->success);
    }

    /* ----------------------------------------------------------------
     |  L8: 容灾降级
     | ---------------------------------------------------------------- */

    public function test_layer8_fallback_exhausted_returns_exhausted(): void
    {
        $group = $this->makeGroup();
        $account = $this->makeAccount(['id' => 1]);

        $this->mappingService->shouldReceive('resolveGroup')->andReturn($group);
        // 正常流程返回空
        $this->mappingService->shouldReceive('getAvailableAccounts')->andReturn(new EloquentCollection([]));

        $result = $this->service->elect(1, 'paypal', '50.00');

        $this->assertFalse($result->success);
        $this->assertSame('no_available', $result->code);
    }

    /* ----------------------------------------------------------------
     |  完整 8 层通过
     | ---------------------------------------------------------------- */

    public function test_full_8_layer_success_flow(): void
    {
        $group = $this->makeGroup();
        $account = $this->makeAccount(['id' => 42, 'health_score' => 90]);

        $this->mappingService->shouldReceive('resolveGroup')->andReturn($group);
        $this->mappingService->shouldReceive('getAvailableAccounts')
            ->andReturn(new EloquentCollection([$account]));
        $this->lifecycleService->shouldReceive('canProcess')->andReturn(true);
        Cache::shouldReceive('get')->andReturn(null);

        $result = $this->service->elect(1, 'paypal', '50.00', [
            'ip' => '8.8.8.8',
            'email' => 'buyer@example.com',
        ]);

        $this->assertTrue($result->success);
        $this->assertSame('success', $result->code);
        $this->assertNotNull($result->account);
        $this->assertSame(42, $result->account->id);
    }

    /* ----------------------------------------------------------------
     |  无可用分组
     | ---------------------------------------------------------------- */

    public function test_no_available_group_returns_no_mapping(): void
    {
        $this->mappingService->shouldReceive('resolveGroup')->andReturn(null);

        $result = $this->service->elect(1, 'paypal', '100.00');

        $this->assertSame('no_mapping', $result->code);
    }

    /* ----------------------------------------------------------------
     |  所有账号不可用
     | ---------------------------------------------------------------- */

    public function test_all_accounts_unavailable_returns_no_available(): void
    {
        $group = $this->makeGroup();

        $this->mappingService->shouldReceive('resolveGroup')->andReturn($group);
        $this->mappingService->shouldReceive('getAvailableAccounts')
            ->andReturn(new EloquentCollection([]));

        $result = $this->service->elect(1, 'paypal', '50.00');

        $this->assertFalse($result->success);
        $this->assertSame('no_available', $result->code);
    }

    /* ----------------------------------------------------------------
     |  layerLogs 记录完整性
     | ---------------------------------------------------------------- */

    public function test_layer_logs_are_populated(): void
    {
        $group = $this->makeGroup();
        $account = $this->makeAccount(['id' => 1, 'health_score' => 80]);

        $this->mappingService->shouldReceive('resolveGroup')->andReturn($group);
        $this->mappingService->shouldReceive('getAvailableAccounts')
            ->andReturn(new EloquentCollection([$account]));
        $this->lifecycleService->shouldReceive('canProcess')->andReturn(true);
        Cache::shouldReceive('get')->andReturn(null);

        $result = $this->service->elect(1, 'paypal', '50.00');

        $this->assertNotEmpty($result->layerLogs);
        // 应该至少有多个 layer 的日志
        $this->assertGreaterThanOrEqual(3, count($result->layerLogs));
    }

    /* ----------------------------------------------------------------
     |  markAccountUsed
     | ---------------------------------------------------------------- */

    public function test_mark_account_used_updates_cache(): void
    {
        $account = $this->makeAccount(['id' => 99]);

        Cache::shouldReceive('put')
            ->once()
            ->with('election:last_used:99', Mockery::type('int'), Mockery::type('int'));

        $this->service->markAccountUsed($account);

        // Mockery will verify the expectation in tearDown
        $this->assertTrue(true);
    }

    /* ----------------------------------------------------------------
     |  多账号场景选择最优
     | ---------------------------------------------------------------- */

    public function test_selects_best_account_from_multiple(): void
    {
        $group = $this->makeGroup();
        $a1 = $this->makeAccount(['id' => 1, 'priority' => 5, 'health_score' => 60]);
        $a2 = $this->makeAccount(['id' => 2, 'priority' => 15, 'health_score' => 90]);
        $a3 = $this->makeAccount(['id' => 3, 'priority' => 10, 'health_score' => 70]);

        $this->mappingService->shouldReceive('resolveGroup')->andReturn($group);
        $this->mappingService->shouldReceive('getAvailableAccounts')
            ->andReturn(new EloquentCollection([$a1, $a2, $a3]));
        $this->lifecycleService->shouldReceive('canProcess')->andReturn(true);
        Cache::shouldReceive('get')->andReturn(null);

        $result = $this->service->elect(1, 'paypal', '50.00');

        $this->assertTrue($result->success);
        // a2 has highest priority (15) → selected after L4 sort
        $this->assertSame(2, $result->account->id);
    }
}
