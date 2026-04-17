<?php

declare(strict_types=1);

namespace App\DTOs;

/**
 * 特货识别结果 DTO（M4-003）
 *
 * 封装三级判定引擎的单商品判定结果。
 */
class SensitivityResult
{
    public function __construct(
        public readonly bool $isSensitive,
        public readonly string $level,       // 'sku' | 'brand' | 'category' | 'none'
        public readonly int $confidence,     // 0-100
        public readonly ?string $matchedRule = null, // 匹配的具体规则（如 SKU 前缀 'hic'）
    ) {}

    /**
     * 创建「敏感」结果
     */
    public static function sensitive(string $level, int $confidence, string $rule): self
    {
        return new self(
            isSensitive: true,
            level:       $level,
            confidence:  $confidence,
            matchedRule: $rule,
        );
    }

    /**
     * 创建「安全」结果
     */
    public static function safe(): self
    {
        return new self(
            isSensitive: false,
            level:       'none',
            confidence:  0,
            matchedRule: null,
        );
    }

    /**
     * 转换为数组
     */
    public function toArray(): array
    {
        return [
            'is_sensitive' => $this->isSensitive,
            'level'        => $this->level,
            'confidence'   => $this->confidence,
            'matched_rule' => $this->matchedRule,
        ];
    }
}
