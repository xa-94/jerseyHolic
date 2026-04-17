<?php

declare(strict_types=1);

namespace App\DTOs;

/**
 * 订单特货分析结果 DTO（M4-003）
 *
 * 封装混合订单策略（BR-MIX-001~004）的分析结果。
 *
 * 策略说明：
 *  - BR-MIX-001：订单中存在任一特货商品 → 全订单标记为特货
 *  - BR-MIX-002：全订单使用安全映射名称（包括普货商品）
 *  - BR-MIX-003：支付描述统一使用安全描述
 *  - BR-MIX-004：物流申报使用安全品名
 */
class OrderSensitivityResult
{
    /**
     * @param  bool                  $hasSensitiveItems  订单是否包含特货商品
     * @param  bool                  $requireSafeMapping 是否需要安全映射（BR-MIX-002）
     * @param  array<SensitivityResult> $itemResults     每个商品的判定结果
     * @param  string                $overallStrategy    整体策略：'all_safe' | 'normal'
     */
    public function __construct(
        public readonly bool $hasSensitiveItems,
        public readonly bool $requireSafeMapping,
        public readonly array $itemResults,
        public readonly string $overallStrategy,
    ) {}

    /**
     * 创建「含特货」的订单结果
     *
     * @param  array<SensitivityResult> $itemResults
     */
    public static function sensitive(array $itemResults): self
    {
        return new self(
            hasSensitiveItems: true,
            requireSafeMapping: true,     // BR-MIX-002
            itemResults:        $itemResults,
            overallStrategy:    'all_safe', // BR-MIX-001: 全订单安全映射
        );
    }

    /**
     * 创建「全普货」的订单结果
     *
     * @param  array<SensitivityResult> $itemResults
     */
    public static function normal(array $itemResults): self
    {
        return new self(
            hasSensitiveItems: false,
            requireSafeMapping: false,
            itemResults:        $itemResults,
            overallStrategy:    'normal',
        );
    }

    /**
     * 转换为数组
     */
    public function toArray(): array
    {
        return [
            'has_sensitive_items' => $this->hasSensitiveItems,
            'require_safe_mapping' => $this->requireSafeMapping,
            'overall_strategy'    => $this->overallStrategy,
            'item_results'        => array_map(
                fn (SensitivityResult $r) => $r->toArray(),
                $this->itemResults,
            ),
        ];
    }
}
