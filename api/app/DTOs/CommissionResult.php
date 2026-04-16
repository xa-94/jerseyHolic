<?php

namespace App\DTOs;

/**
 * 佣金计算结果 DTO
 *
 * 封装单笔订单佣金计算的完整结果，包含各项费率和金额。
 * 所有金额/费率字段均为 string 类型，保证 bcmath 精度。
 */
class CommissionResult
{
    public function __construct(
        /** 订单金额（USD） */
        public readonly string $orderAmount,
        /** 基础费率（百分比，如 15.00 = 15%） */
        public readonly string $baseRate,
        /** 成交量奖励减免（百分比） */
        public readonly string $volumeDiscount,
        /** 忠诚度奖励减免（百分比） */
        public readonly string $loyaltyDiscount,
        /** 最终费率（百分比，已应用 min/max 约束） */
        public readonly string $effectiveRate,
        /** 佣金金额（USD） */
        public readonly string $commissionAmount,
        /** 命中的佣金规则 ID（null 表示无匹配规则） */
        public readonly ?int $ruleId = null,
    ) {}

    /**
     * 转换为数组
     */
    public function toArray(): array
    {
        return [
            'order_amount'     => $this->orderAmount,
            'base_rate'        => $this->baseRate,
            'volume_discount'  => $this->volumeDiscount,
            'loyalty_discount' => $this->loyaltyDiscount,
            'effective_rate'   => $this->effectiveRate,
            'commission_amount' => $this->commissionAmount,
            'rule_id'          => $this->ruleId,
        ];
    }
}
