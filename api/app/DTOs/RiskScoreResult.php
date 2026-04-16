<?php

namespace App\DTOs;

/**
 * 商户风险评分结果 DTO
 *
 * 封装 MerchantRiskService 五维度加权评分结果，
 * 包含总分、风险等级、各维度明细及自动执行的操作。
 *
 * 风险等级：
 *  - low      (0-30)   正常
 *  - medium   (31-60)  关注
 *  - high     (61-80)  高风险 → 限额下调
 *  - critical (81-100) 极高风险 → 暂停交易 + 自动加黑名单
 */
class RiskScoreResult
{
    public function __construct(
        public readonly int $merchantId,
        public readonly int $totalScore,
        public readonly string $level,
        public readonly array $dimensions,
        public readonly array $actions = [],
    ) {}

    /* ----------------------------------------------------------------
     |  静态工厂方法
     | ---------------------------------------------------------------- */

    /**
     * 从评分数据构建结果
     */
    public static function fromScore(int $merchantId, int $score, array $dimensions, array $actions = []): self
    {
        return new self(
            merchantId: $merchantId,
            totalScore: $score,
            level:      self::scoreToLevel($score),
            dimensions: $dimensions,
            actions:    $actions,
        );
    }

    /**
     * 分数 → 风险等级
     */
    public static function scoreToLevel(int $score): string
    {
        return match (true) {
            $score <= 30  => 'low',
            $score <= 60  => 'medium',
            $score <= 80  => 'high',
            default       => 'critical',
        };
    }

    /* ----------------------------------------------------------------
     |  辅助方法
     | ---------------------------------------------------------------- */

    public function isHighRisk(): bool
    {
        return in_array($this->level, ['high', 'critical'], true);
    }

    public function isCritical(): bool
    {
        return $this->level === 'critical';
    }

    /**
     * 转为数组（API / 日志用）
     */
    public function toArray(): array
    {
        return [
            'merchant_id' => $this->merchantId,
            'total_score' => $this->totalScore,
            'level'       => $this->level,
            'dimensions'  => $this->dimensions,
            'actions'     => $this->actions,
        ];
    }
}
