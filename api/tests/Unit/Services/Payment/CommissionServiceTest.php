<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Payment;

use App\DTOs\CommissionResult;
use App\Models\Central\CommissionRule;
use App\Models\Central\Merchant;
use App\Services\Payment\CommissionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CommissionServiceTest extends TestCase
{
    use RefreshDatabase;

    private CommissionService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new CommissionService();
    }

    /* ----------------------------------------------------------------
     |  基础费率
     | ---------------------------------------------------------------- */

    public function test_basic_rate_calculation_returns_correct_commission(): void
    {
        // 创建全局规则 base_rate = 15%
        CommissionRule::create([
            'merchant_id' => null,
            'store_id'    => null,
            'rule_type'   => CommissionRule::RULE_TYPE_DEFAULT,
            'tier_name'   => 'Global Default',
            'base_rate'   => '15.00',
            'min_rate'    => '8.00',
            'max_rate'    => '35.00',
            'enabled'     => CommissionRule::ENABLED,
        ]);

        $result = $this->service->calculate(999, null, '100.00');

        $this->assertInstanceOf(CommissionResult::class, $result);
        $this->assertSame('15.00', $result->effectiveRate);
        // 佣金 = 100 * 15/100 = 15.00
        $this->assertSame('15.00', $result->commissionAmount);
    }

    /* ----------------------------------------------------------------
     |  成交量奖励阶梯折扣
     | ---------------------------------------------------------------- */

    public function test_volume_discount_applied_for_high_gmv(): void
    {
        CommissionRule::create([
            'merchant_id' => null, 'store_id' => null,
            'rule_type' => 'default', 'tier_name' => 'Global',
            'base_rate' => '15.00', 'min_rate' => '8.00', 'max_rate' => '35.00',
            'enabled' => 1,
        ]);

        // monthlyGmv = 25000 → volume discount = 3%
        $result = $this->service->calculate(999, null, '1000.00', 25000.00);

        // effectiveRate = 15 - 3 - 0(no loyalty) = 12
        $this->assertSame('12.00', $result->effectiveRate);
        $this->assertSame('3.00', $result->volumeDiscount);
    }

    /* ----------------------------------------------------------------
     |  忠诚度折扣
     | ---------------------------------------------------------------- */

    public function test_loyalty_discount_applied_for_long_cooperation(): void
    {
        $merchant = Merchant::create([
            'merchant_name' => 'Loyal Merchant',
            'email' => 'loyal@test.com',
            'password' => bcrypt('password'),
            'contact_name' => 'Test',
            'phone' => '+1234567890',
            'level' => 'starter',
            'status' => 1,
            'approved_at' => now()->subMonths(13),
        ]);

        CommissionRule::create([
            'merchant_id' => null, 'store_id' => null,
            'rule_type' => 'default', 'tier_name' => 'Global',
            'base_rate' => '15.00', 'min_rate' => '8.00', 'max_rate' => '35.00',
            'enabled' => 1,
        ]);

        $result = $this->service->calculate($merchant->id, null, '1000.00');

        // 13 months → loyalty discount = 2%
        $this->assertSame('2.00', $result->loyaltyDiscount);
        // effectiveRate = 15 - 0 - 2 = 13
        $this->assertSame('13.00', $result->effectiveRate);
    }

    /* ----------------------------------------------------------------
     |  费率上限约束
     | ---------------------------------------------------------------- */

    public function test_effective_rate_capped_at_max_rate(): void
    {
        CommissionRule::create([
            'merchant_id' => null, 'store_id' => null,
            'rule_type' => 'default', 'tier_name' => 'High Rate',
            'base_rate' => '40.00', 'min_rate' => '8.00', 'max_rate' => '35.00',
            'enabled' => 1,
        ]);

        $result = $this->service->calculate(999, null, '100.00');

        $this->assertSame('35.00', $result->effectiveRate);
    }

    /* ----------------------------------------------------------------
     |  费率下限约束
     | ---------------------------------------------------------------- */

    public function test_effective_rate_floored_at_min_rate(): void
    {
        $merchant = Merchant::create([
            'merchant_name' => 'Min Rate Merchant',
            'email' => 'min@test.com',
            'password' => bcrypt('password'),
            'contact_name' => 'Test',
            'phone' => '+1234567890',
            'level' => 'starter',
            'status' => 1,
            'approved_at' => now()->subMonths(13),
        ]);

        CommissionRule::create([
            'merchant_id' => null, 'store_id' => null,
            'rule_type' => 'default', 'tier_name' => 'Low Rate',
            'base_rate' => '10.00', 'min_rate' => '8.00', 'max_rate' => '35.00',
            'enabled' => 1,
        ]);

        // base=10, volume=5(GMV>100k), loyalty=2(13months) → 10-5-2=3 → clamped to 8
        $result = $this->service->calculate($merchant->id, null, '100.00', 150000.00);

        $this->assertSame('8.00', $result->effectiveRate);
    }

    /* ----------------------------------------------------------------
     |  三级优先级
     | ---------------------------------------------------------------- */

    public function test_store_rule_takes_priority_over_merchant_and_global(): void
    {
        // 全局规则
        CommissionRule::create([
            'merchant_id' => null, 'store_id' => null,
            'rule_type' => 'default', 'tier_name' => 'Global',
            'base_rate' => '15.00', 'min_rate' => '8.00', 'max_rate' => '35.00',
            'enabled' => 1,
        ]);
        // 商户级规则
        CommissionRule::create([
            'merchant_id' => 1, 'store_id' => null,
            'rule_type' => 'default', 'tier_name' => 'Merchant',
            'base_rate' => '12.00', 'min_rate' => '8.00', 'max_rate' => '35.00',
            'enabled' => 1,
        ]);
        // 站点级规则
        CommissionRule::create([
            'merchant_id' => 1, 'store_id' => 10,
            'rule_type' => 'default', 'tier_name' => 'Store',
            'base_rate' => '10.00', 'min_rate' => '8.00', 'max_rate' => '35.00',
            'enabled' => 1,
        ]);

        $result = $this->service->calculate(1, 10, '100.00');

        // 站点级规则 base_rate=10 应生效
        $this->assertSame('10.00', $result->effectiveRate);
    }

    /* ----------------------------------------------------------------
     |  bcmath 精度验证
     | ---------------------------------------------------------------- */

    public function test_bcmath_precision_intermediate_4_output_2(): void
    {
        CommissionRule::create([
            'merchant_id' => null, 'store_id' => null,
            'rule_type' => 'default', 'tier_name' => 'Precision',
            'base_rate' => '15.00', 'min_rate' => '8.00', 'max_rate' => '35.00',
            'enabled' => 1,
        ]);

        // 15% of 99.99 → rate decimal = 0.1500, commission = 99.99 * 0.1500 = 14.9985 → 14.99
        $result = $this->service->calculate(999, null, '99.99');

        $this->assertSame('14.99', $result->commissionAmount);
    }

    /* ----------------------------------------------------------------
     |  零订单金额
     | ---------------------------------------------------------------- */

    public function test_zero_order_amount_returns_zero_commission(): void
    {
        CommissionRule::create([
            'merchant_id' => null, 'store_id' => null,
            'rule_type' => 'default', 'tier_name' => 'Global',
            'base_rate' => '15.00', 'min_rate' => '8.00', 'max_rate' => '35.00',
            'enabled' => 1,
        ]);

        $result = $this->service->calculate(999, null, '0.00');

        $this->assertSame('0.00', $result->commissionAmount);
    }

    /* ----------------------------------------------------------------
     |  极大金额
     | ---------------------------------------------------------------- */

    public function test_very_large_amount_calculates_correctly(): void
    {
        CommissionRule::create([
            'merchant_id' => null, 'store_id' => null,
            'rule_type' => 'default', 'tier_name' => 'Global',
            'base_rate' => '15.00', 'min_rate' => '8.00', 'max_rate' => '35.00',
            'enabled' => 1,
        ]);

        // 1,000,000.00 * 0.15 = 150,000.00
        $result = $this->service->calculate(999, null, '1000000.00');

        $this->assertSame('150000.00', $result->commissionAmount);
    }

    /* ----------------------------------------------------------------
     |  无匹配规则
     | ---------------------------------------------------------------- */

    public function test_no_matching_rule_returns_zero_commission(): void
    {
        // 没有创建任何规则
        $result = $this->service->calculate(999, null, '100.00');

        $this->assertSame('0.00', $result->effectiveRate);
        $this->assertSame('0.00', $result->commissionAmount);
        $this->assertNull($result->ruleId);
    }

    /* ----------------------------------------------------------------
     |  commission = orderAmount × effectiveRate
     | ---------------------------------------------------------------- */

    public function test_commission_equals_amount_times_rate(): void
    {
        CommissionRule::create([
            'merchant_id' => null, 'store_id' => null,
            'rule_type' => 'default', 'tier_name' => 'Global',
            'base_rate' => '20.00', 'min_rate' => '8.00', 'max_rate' => '35.00',
            'enabled' => 1,
        ]);

        $result = $this->service->calculate(999, null, '500.00');

        // 500 * 20/100 = 100.00
        $this->assertSame('100.00', $result->commissionAmount);
        $this->assertSame('500.00', $result->orderAmount);
    }

    /* ----------------------------------------------------------------
     |  成交量折扣阶梯验证
     | ---------------------------------------------------------------- */

    public function test_volume_discount_tiers(): void
    {
        // GMV < 1000 → 0%
        $this->assertSame('0.00', $this->service->calculateVolumeDiscount('500.00'));
        // GMV = 1000 → 1%
        $this->assertSame('1.00', $this->service->calculateVolumeDiscount('1000.00'));
        // GMV = 5000 → 2%
        $this->assertSame('2.00', $this->service->calculateVolumeDiscount('5000.00'));
        // GMV = 20000 → 3%
        $this->assertSame('3.00', $this->service->calculateVolumeDiscount('20000.00'));
        // GMV = 100000 → 5%
        $this->assertSame('5.00', $this->service->calculateVolumeDiscount('100000.00'));
    }

    /* ----------------------------------------------------------------
     |  不同货币金额处理（仍以 string 金额计算）
     | ---------------------------------------------------------------- */

    public function test_different_currency_amount_calculation(): void
    {
        CommissionRule::create([
            'merchant_id' => null, 'store_id' => null,
            'rule_type' => 'default', 'tier_name' => 'Global',
            'base_rate' => '15.00', 'min_rate' => '8.00', 'max_rate' => '35.00',
            'enabled' => 1,
        ]);

        // 即使是非 USD 金额，计算逻辑不变（转换在外部完成）
        $result = $this->service->calculate(999, null, '8888.88');

        // 8888.88 * 0.15 = 1333.332 → 1333.33
        $this->assertSame('1333.33', $result->commissionAmount);
    }

    /* ----------------------------------------------------------------
     |  费率边界值 8% 和 35%
     | ---------------------------------------------------------------- */

    public function test_rate_boundary_at_exactly_8_percent(): void
    {
        CommissionRule::create([
            'merchant_id' => null, 'store_id' => null,
            'rule_type' => 'default', 'tier_name' => 'Boundary',
            'base_rate' => '8.00', 'min_rate' => '8.00', 'max_rate' => '35.00',
            'enabled' => 1,
        ]);

        $result = $this->service->calculate(999, null, '100.00');

        $this->assertSame('8.00', $result->effectiveRate);
        $this->assertSame('8.00', $result->commissionAmount);
    }

    public function test_rate_boundary_at_exactly_35_percent(): void
    {
        CommissionRule::create([
            'merchant_id' => null, 'store_id' => null,
            'rule_type' => 'default', 'tier_name' => 'Boundary',
            'base_rate' => '35.00', 'min_rate' => '8.00', 'max_rate' => '35.00',
            'enabled' => 1,
        ]);

        $result = $this->service->calculate(999, null, '100.00');

        $this->assertSame('35.00', $result->effectiveRate);
    }

    /* ----------------------------------------------------------------
     |  同时有成交量和忠诚度奖励
     | ---------------------------------------------------------------- */

    public function test_both_volume_and_loyalty_discount_applied(): void
    {
        $merchant = Merchant::create([
            'merchant_name' => 'Both Discounts',
            'email' => 'both@test.com',
            'password' => bcrypt('password'),
            'contact_name' => 'Test',
            'phone' => '+1234567890',
            'level' => 'starter',
            'status' => 1,
            'approved_at' => now()->subMonths(7), // loyalty = 1%
        ]);

        CommissionRule::create([
            'merchant_id' => null, 'store_id' => null,
            'rule_type' => 'default', 'tier_name' => 'Global',
            'base_rate' => '15.00', 'min_rate' => '8.00', 'max_rate' => '35.00',
            'enabled' => 1,
        ]);

        // volume = 2% (GMV 5000), loyalty = 1% (7 months)
        $result = $this->service->calculate($merchant->id, null, '1000.00', 5000.00);

        $this->assertSame('2.00', $result->volumeDiscount);
        $this->assertSame('1.00', $result->loyaltyDiscount);
        // effectiveRate = 15 - 2 - 1 = 12
        $this->assertSame('12.00', $result->effectiveRate);
    }

    /* ----------------------------------------------------------------
     |  规则过期/禁用时
     | ---------------------------------------------------------------- */

    public function test_disabled_rule_is_not_used(): void
    {
        CommissionRule::create([
            'merchant_id' => null, 'store_id' => null,
            'rule_type' => 'default', 'tier_name' => 'Disabled',
            'base_rate' => '15.00', 'min_rate' => '8.00', 'max_rate' => '35.00',
            'enabled' => CommissionRule::DISABLED,
        ]);

        $result = $this->service->calculate(999, null, '100.00');

        // 禁用规则不匹配，回退到零佣金
        $this->assertSame('0.00', $result->commissionAmount);
        $this->assertNull($result->ruleId);
    }
}
