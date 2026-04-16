<?php

namespace App\DTOs;

use App\Models\Central\PaypalSafeDescription;

/**
 * 安全商品描述数据传输对象
 *
 * 封装经脱敏处理后的商品名称、描述和分类代码，
 * 用于 PayPal/Stripe 支付创建和物流面单生成等场景。
 */
class SafeDescriptionDTO
{
    public function __construct(
        public readonly string $safeName,
        public readonly string $safeDescription,
        public readonly string $safeCategoryCode = ''
    ) {}

    /**
     * 从 PaypalSafeDescription 模型构建 DTO
     */
    public static function fromModel(PaypalSafeDescription $model): self
    {
        return new self(
            safeName:         $model->safe_name,
            safeDescription:  $model->safe_description,
            safeCategoryCode: $model->safe_category_code ?? '',
        );
    }

    /**
     * 转换为数组
     */
    public function toArray(): array
    {
        return [
            'safe_name'          => $this->safeName,
            'safe_description'   => $this->safeDescription,
            'safe_category_code' => $this->safeCategoryCode,
        ];
    }
}
