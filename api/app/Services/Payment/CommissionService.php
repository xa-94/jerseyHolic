<?php

namespace App\Services\Payment;

use App\DTOs\CommissionResult;
use App\Models\Central\CommissionRule;
use App\Models\Central\Merchant;
use Illuminate\Support\Facades\Log;

/**
 * 佣金计算核心服务（M3-012）
 *
 * 负责根据佣金规则计算单笔订单的佣金金额。
 * 核心公式：最终佣金率 = 基础佣金率(等级) - 成交量奖励 - 忠诚度奖励
 * 约束：min_rate ≤ 最终佣金率 ≤ max_rate（默认 [8%, 35%]）
 * 佣金金额 = 订单金额(USD) × 最终佣金率 / 100
 *
 * 所有金额/费率计算均使用 bcmath 库，禁止浮点运算。
 */
class CommissionService
{
    /** bcmath 精度：费率 2 位，金额 2 位 */
    private const RATE_SCALE   = 2;
    private const AMOUNT_SCALE = 2;

    /** 默认费率上下限（百分比） */
    private const DEFAULT_MIN_RATE = '8.00';
    private const DEFAULT_MAX_RATE = '35.00';

    /** 成交量奖励阶梯（月 GMV → 奖励百分比） */
    private const VOLUME_DISCOUNT_TIERS = [
        ['min' => '100000.00', 'discount' => '5.00'],
        ['min' => '20000.00',  'discount' => '3.00'],
        ['min' => '5000.00',   'discount' => '2.00'],
        ['min' => '1000.00',   'discount' => '1.00'],
    ];

    /** 忠诚度奖励阶梯（合作月数 → 奖励百分比） */
    private const LOYALTY_DISCOUNT_TIERS = [
        ['min_months' => 12, 'discount' => '2.00'],
        ['min_months' => 6,  'discount' => '1.00'],
        ['min_months' => 3,  'discount' => '0.50'],
    ];

    /* ----------------------------------------------------------------
     |  核心计算
     | ---------------------------------------------------------------- */

    /**
     * 计算单笔订单的佣金
     *
     * @param  int         $merchantId  商户 ID
     * @param  int|null    $storeId     站点 ID（可选，用于站点级规则匹配）
     * @param  string      $amount      订单金额（USD，字符串精度）
     * @param  float|null  $monthlyGmv  当月 GMV（可选，用于成交量奖励计算）
     * @return CommissionResult
     */
    public function calculate(int $merchantId, ?int $storeId, string $amount, ?float $monthlyGmv = null): CommissionResult
    {
        // 1. 查找适用规则（三级优先级）
        $rule = $this->findApplicableRule($merchantId, $storeId);

        if ($rule === null) {
            Log::warning('[CommissionService] No applicable commission rule found.', [
                'merchant_id' => $merchantId,
                'store_id'    => $storeId,
            ]);

            // 无规则时返回零佣金
            return new CommissionResult(
                orderAmount:     $amount,
                baseRate:        '0.00',
                volumeDiscount:  '0.00',
                loyaltyDiscount: '0.00',
                effectiveRate:   '0.00',
                commissionAmount: '0.00',
                ruleId:          null,
            );
        }

        // 2. 获取基础费率
        $baseRate = bcadd($rule->base_rate, '0', self::RATE_SCALE);

        // 3. 计算成交量奖励
        $gmvStr = $monthlyGmv !== null
            ? number_format($monthlyGmv, self::AMOUNT_SCALE, '.', '')
            : '0.00';
        $volumeDiscount = $this->calculateVolumeDiscount($gmvStr);

        // 4. 计算忠诚度奖励
        $loyaltyDiscount = $this->calculateLoyaltyDiscount($merchantId);

        // 5. 计算有效费率：base_rate - volume_discount - loyalty_discount
        $effectiveRate = bcsub($baseRate, $volumeDiscount, self::RATE_SCALE);
        $effectiveRate = bcsub($effectiveRate, $loyaltyDiscount, self::RATE_SCALE);

        // 6. 应用 min/max 约束
        $minRate = bcadd($rule->min_rate ?? self::DEFAULT_MIN_RATE, '0', self::RATE_SCALE);
        $maxRate = bcadd($rule->max_rate ?? self::DEFAULT_MAX_RATE, '0', self::RATE_SCALE);

        if (bccomp($effectiveRate, $minRate, self::RATE_SCALE) < 0) {
            $effectiveRate = $minRate;
        }
        if (bccomp($effectiveRate, $maxRate, self::RATE_SCALE) > 0) {
            $effectiveRate = $maxRate;
        }

        // 7. 计算佣金金额 = 订单金额 × (有效费率 / 100)
        $rateDecimal     = bcdiv($effectiveRate, '100', 4); // 中间精度 4 位
        $commissionAmount = bcmul($amount, $rateDecimal, self::AMOUNT_SCALE);

        Log::info('[CommissionService] Commission calculated.', [
            'merchant_id'       => $merchantId,
            'store_id'          => $storeId,
            'order_amount'      => $amount,
            'rule_id'           => $rule->id,
            'base_rate'         => $baseRate,
            'volume_discount'   => $volumeDiscount,
            'loyalty_discount'  => $loyaltyDiscount,
            'effective_rate'    => $effectiveRate,
            'commission_amount' => $commissionAmount,
        ]);

        return new CommissionResult(
            orderAmount:      $amount,
            baseRate:         $baseRate,
            volumeDiscount:   $volumeDiscount,
            loyaltyDiscount:  $loyaltyDiscount,
            effectiveRate:    $effectiveRate,
            commissionAmount: $commissionAmount,
            ruleId:           $rule->id,
        );
    }

    /* ----------------------------------------------------------------
     |  规则查找（三级优先级）
     | ---------------------------------------------------------------- */

    /**
     * 查找适用的佣金规则
     *
     * 三级优先级：站点级 > 商户级 > 全局
     * 仅返回已启用的规则。
     *
     * @param  int      $merchantId
     * @param  int|null $storeId
     * @return CommissionRule|null
     */
    public function findApplicableRule(int $merchantId, ?int $storeId): ?CommissionRule
    {
        // 优先级 1：站点级规则
        if ($storeId !== null) {
            $storeRule = CommissionRule::enabled()
                ->forStore($storeId)
                ->first();

            if ($storeRule !== null) {
                return $storeRule;
            }
        }

        // 优先级 2：商户级规则
        $merchantRule = CommissionRule::enabled()
            ->forMerchant($merchantId)
            ->first();

        if ($merchantRule !== null) {
            return $merchantRule;
        }

        // 优先级 3：全局规则
        return CommissionRule::enabled()
            ->global()
            ->first();
    }

    /* ----------------------------------------------------------------
     |  奖励计算
     | ---------------------------------------------------------------- */

    /**
     * 计算成交量奖励（按月 GMV 阶梯）
     *
     * | 月 GMV           | 奖励减免 |
     * |------------------|---------|
     * | < $1,000         | 0%      |
     * | $1,000 ~ $5,000  | 1%      |
     * | $5,000 ~ $20,000 | 2%      |
     * | $20,000 ~ $100k  | 3%      |
     * | > $100,000       | 5%      |
     *
     * @param  string $monthlyGmv  当月 GMV（字符串精度）
     * @return string 奖励百分比（如 "2.00"）
     */
    public function calculateVolumeDiscount(string $monthlyGmv): string
    {
        foreach (self::VOLUME_DISCOUNT_TIERS as $tier) {
            if (bccomp($monthlyGmv, $tier['min'], self::AMOUNT_SCALE) >= 0) {
                return $tier['discount'];
            }
        }

        return '0.00';
    }

    /**
     * 计算忠诚度奖励（按合作月数阶梯）
     *
     * | 合作时长   | 奖励减免 |
     * |-----------|---------|
     * | < 3 个月  | 0%      |
     * | 3-6 个月  | 0.5%    |
     * | 6-12 个月 | 1%      |
     * | > 12 个月 | 2%      |
     *
     * @param  int    $merchantId
     * @return string 奖励百分比（如 "1.00"）
     */
    public function calculateLoyaltyDiscount(int $merchantId): string
    {
        $merchant = Merchant::find($merchantId);

        if ($merchant === null || $merchant->approved_at === null) {
            return '0.00';
        }

        $monthsCooperated = (int) $merchant->approved_at->diffInMonths(now());

        foreach (self::LOYALTY_DISCOUNT_TIERS as $tier) {
            if ($monthsCooperated >= $tier['min_months']) {
                return $tier['discount'];
            }
        }

        return '0.00';
    }
}
