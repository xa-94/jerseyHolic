<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Payment;

use App\Models\Central\PaymentAccount;
use App\Services\Payment\AccountHealthScoreService;
use App\Services\Payment\AccountLifecycleService;
use Tests\TestCase;

class AccountLifecycleTest extends TestCase
{
    private AccountLifecycleService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new AccountLifecycleService();
    }

    private function makeAccount(array $attrs = []): PaymentAccount
    {
        $account = new PaymentAccount(array_merge([
            'id'                  => 1,
            'account'             => 'test@paypal.com',
            'status'              => 1,
            'permission'          => 1,
            'lifecycle_stage'     => PaymentAccount::LIFECYCLE_NEW,
            'health_score'        => 80,
            'single_limit'        => '50.00',
            'daily_limit'         => '100.00',
            'monthly_limit'       => '500.00',
            'daily_count_limit'   => 3,
            'daily_money_total'   => '0.00',
            'deal_count'          => 0,
            'total_success_count' => 0,
            'total_fail_count'    => 0,
            'total_refund_count'  => 0,
            'total_dispute_count' => 0,
            'cooling_until'       => null,
            'last_used_at'        => null,
            'created_at'          => now()->subDays(1),
        ], $attrs));
        $account->exists = true;

        return $account;
    }

    /* ----------------------------------------------------------------
     |  NEW 阶段限额正确
     | ---------------------------------------------------------------- */

    public function test_new_stage_limits_correct(): void
    {
        $limits = $this->service->getStageLimits(PaymentAccount::LIFECYCLE_NEW);

        $this->assertSame('50.00', $limits['single_limit']);
        $this->assertSame('100.00', $limits['daily_limit']);
        $this->assertSame('500.00', $limits['monthly_limit']);
        $this->assertSame(3, $limits['daily_count_limit']);
    }

    /* ----------------------------------------------------------------
     |  GROWING 阶段限额
     | ---------------------------------------------------------------- */

    public function test_growing_stage_limits_correct(): void
    {
        $limits = $this->service->getStageLimits(PaymentAccount::LIFECYCLE_GROWING);

        $this->assertSame('200.00', $limits['single_limit']);
        $this->assertSame('500.00', $limits['daily_limit']);
        $this->assertSame('5000.00', $limits['monthly_limit']);
        $this->assertSame(10, $limits['daily_count_limit']);
    }

    /* ----------------------------------------------------------------
     |  MATURE 阶段限额
     | ---------------------------------------------------------------- */

    public function test_mature_stage_limits_correct(): void
    {
        $limits = $this->service->getStageLimits(PaymentAccount::LIFECYCLE_MATURE);

        $this->assertSame('2000.00', $limits['single_limit']);
        $this->assertSame('10000.00', $limits['daily_limit']);
        $this->assertSame('100000.00', $limits['monthly_limit']);
        $this->assertSame(50, $limits['daily_count_limit']);
    }

    /* ----------------------------------------------------------------
     |  各阶段限额递增验证
     | ---------------------------------------------------------------- */

    public function test_limits_increase_across_stages(): void
    {
        $newLimits = $this->service->getStageLimits(PaymentAccount::LIFECYCLE_NEW);
        $growLimits = $this->service->getStageLimits(PaymentAccount::LIFECYCLE_GROWING);
        $matureLimits = $this->service->getStageLimits(PaymentAccount::LIFECYCLE_MATURE);

        // single_limit: NEW < GROWING < MATURE
        $this->assertGreaterThan(
            (float) $newLimits['single_limit'],
            (float) $growLimits['single_limit']
        );
        $this->assertGreaterThan(
            (float) $growLimits['single_limit'],
            (float) $matureLimits['single_limit']
        );

        // daily_count_limit: NEW < GROWING < MATURE
        $this->assertGreaterThan($newLimits['daily_count_limit'], $growLimits['daily_count_limit']);
        $this->assertGreaterThan($growLimits['daily_count_limit'], $matureLimits['daily_count_limit']);
    }

    /* ----------------------------------------------------------------
     |  canProcess 验证
     | ---------------------------------------------------------------- */

    public function test_can_process_within_limits(): void
    {
        $account = $this->makeAccount([
            'lifecycle_stage' => PaymentAccount::LIFECYCLE_MATURE,
            'single_limit'    => '2000.00',
            'daily_limit'     => '10000.00',
            'daily_money_total' => '0.00',
            'daily_count_limit' => 50,
            'deal_count'      => 0,
        ]);

        $this->assertTrue($this->service->canProcess($account, '100.00'));
    }

    public function test_cannot_process_aging_account(): void
    {
        $account = $this->makeAccount([
            'lifecycle_stage' => PaymentAccount::LIFECYCLE_AGING,
        ]);

        $this->assertFalse($this->service->canProcess($account, '10.00'));
    }

    public function test_cannot_process_over_single_limit(): void
    {
        $account = $this->makeAccount([
            'lifecycle_stage' => PaymentAccount::LIFECYCLE_NEW,
            'single_limit'    => '50.00',
            'daily_limit'     => '100.00',
            'daily_money_total' => '0.00',
            'daily_count_limit' => 3,
            'deal_count'      => 0,
        ]);

        $this->assertFalse($this->service->canProcess($account, '60.00'));
    }

    public function test_cannot_process_cooling_account(): void
    {
        $account = $this->makeAccount([
            'cooling_until' => now()->addDays(5),
        ]);

        $this->assertFalse($this->service->canProcess($account, '10.00'));
    }

    /* ----------------------------------------------------------------
     |  健康度评分 5 维度加权计算
     | ---------------------------------------------------------------- */

    public function test_health_score_five_dimensions(): void
    {
        $lifecycleService = $this->createMock(AccountLifecycleService::class);
        $healthService = new AccountHealthScoreService($lifecycleService);

        $account = new PaymentAccount([
            'total_success_count' => 100,
            'total_fail_count'    => 0,
            'total_refund_count'  => 0,
            'total_dispute_count' => 0,
            'health_score'        => 80,
            'lifecycle_stage'     => PaymentAccount::LIFECYCLE_MATURE,
            'last_used_at'        => now()->subDays(1),
            'created_at'          => now()->subDays(120),
        ]);

        $breakdown = $healthService->getScoreBreakdown($account);

        // 所有维度分数应存在
        $this->assertArrayHasKey('success_rate_score', $breakdown);
        $this->assertArrayHasKey('refund_rate_score', $breakdown);
        $this->assertArrayHasKey('dispute_rate_score', $breakdown);
        $this->assertArrayHasKey('activity_score', $breakdown);
        $this->assertArrayHasKey('age_score', $breakdown);

        // 完美账号应得高分
        $this->assertGreaterThanOrEqual(90, $breakdown['total']);
    }
}
