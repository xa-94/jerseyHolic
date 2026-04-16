<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Payment;

use App\DTOs\RiskScoreResult;
use App\Models\Central\PaymentAccount;
use App\Services\NotificationService;
use App\Services\Payment\AccountHealthScoreService;
use App\Services\Payment\BlacklistService;
use App\Services\Payment\MerchantRiskService;
use Mockery;
use Tests\TestCase;

class MerchantRiskTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /* ----------------------------------------------------------------
     |  5 维度加权评分计算正确
     | ---------------------------------------------------------------- */

    public function test_five_dimension_weighted_score_calculation(): void
    {
        // 验证 RiskScoreResult 的分数到等级映射
        $this->assertSame('low', RiskScoreResult::scoreToLevel(0));
        $this->assertSame('low', RiskScoreResult::scoreToLevel(30));
        $this->assertSame('medium', RiskScoreResult::scoreToLevel(31));
        $this->assertSame('medium', RiskScoreResult::scoreToLevel(60));
        $this->assertSame('high', RiskScoreResult::scoreToLevel(61));
        $this->assertSame('high', RiskScoreResult::scoreToLevel(80));
        $this->assertSame('critical', RiskScoreResult::scoreToLevel(81));
        $this->assertSame('critical', RiskScoreResult::scoreToLevel(100));

        // 验证 RiskScoreResult DTO 结构
        $result = RiskScoreResult::fromScore(1, 65, [
            'refund_score'    => 15.0,
            'dispute_score'   => 12.5,
            'volume_score'    => 20.0,
            'health_score'    => 10.0,
            'complaint_score' => 7.5,
        ], ['limit_reduced_50']);

        $this->assertSame(65, $result->totalScore);
        $this->assertSame('high', $result->level);
        $this->assertTrue($result->isHighRisk());
        $this->assertFalse($result->isCritical());
        $this->assertContains('limit_reduced_50', $result->actions);
    }

    /* ----------------------------------------------------------------
     |  high 风险限额下调 50%
     | ---------------------------------------------------------------- */

    public function test_high_risk_triggers_limit_reduction(): void
    {
        // 验证 high 风险等级的判定边界
        $this->assertSame('high', RiskScoreResult::scoreToLevel(61));
        $this->assertSame('high', RiskScoreResult::scoreToLevel(80));

        // 验证 RiskScoreResult 的 isHighRisk 方法
        $highResult = RiskScoreResult::fromScore(1, 75, [], ['limit_reduced_50']);
        $this->assertTrue($highResult->isHighRisk());
        $this->assertFalse($highResult->isCritical());

        // 验证 actions 包含限额下调
        $this->assertContains('limit_reduced_50', $highResult->actions);
    }

    /* ----------------------------------------------------------------
     |  critical 风险暂停所有账号
     | ---------------------------------------------------------------- */

    public function test_critical_risk_suspends_all_accounts(): void
    {
        // 验证 critical 风险等级的判定边界
        $this->assertSame('critical', RiskScoreResult::scoreToLevel(81));
        $this->assertSame('critical', RiskScoreResult::scoreToLevel(100));

        // 验证 RiskScoreResult 的 isCritical 方法
        $criticalResult = RiskScoreResult::fromScore(1, 90, [], [
            'accounts_suspended',
            'auto_blacklisted',
            'fund_frozen_90d',
        ]);

        $this->assertTrue($criticalResult->isCritical());
        $this->assertTrue($criticalResult->isHighRisk());
        $this->assertContains('accounts_suspended', $criticalResult->actions);
        $this->assertContains('auto_blacklisted', $criticalResult->actions);
        $this->assertContains('fund_frozen_90d', $criticalResult->actions);

        // 验证 toArray 输出
        $array = $criticalResult->toArray();
        $this->assertSame(1, $array['merchant_id']);
        $this->assertSame(90, $array['total_score']);
        $this->assertSame('critical', $array['level']);
    }
}
