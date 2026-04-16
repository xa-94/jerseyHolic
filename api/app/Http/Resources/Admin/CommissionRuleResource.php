<?php

namespace App\Http\Resources\Admin;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * 佣金规则 API Resource（Admin 端）
 *
 * 用于佣金规则的列表和详情接口输出。
 */
class CommissionRuleResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        return [
            'id'               => $this->id,
            'merchant_id'      => $this->merchant_id,
            'store_id'         => $this->store_id,
            'rule_type'        => $this->rule_type,
            'tier_name'        => $this->tier_name,
            'base_rate'        => (string) $this->base_rate,
            'volume_discount'  => (string) $this->volume_discount,
            'loyalty_discount' => (string) $this->loyalty_discount,
            'min_rate'         => (string) $this->min_rate,
            'max_rate'         => (string) $this->max_rate,
            'effective_rate'   => number_format($this->resource->calculateEffectiveRate(), 2, '.', ''),
            'enabled'          => (bool) $this->enabled,
            'scope'            => $this->resolveScope(),

            // 关联信息（已加载时）
            'merchant' => $this->whenLoaded('merchant', fn () => [
                'id'   => $this->merchant->id,
                'name' => $this->merchant->merchant_name,
            ]),
            'store' => $this->whenLoaded('store', fn () => [
                'id'   => $this->store->id,
                'name' => $this->store->store_name,
            ]),

            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }

    /**
     * 解析规则作用域
     */
    private function resolveScope(): string
    {
        if ($this->store_id !== null) {
            return 'store';
        }

        if ($this->merchant_id !== null) {
            return 'merchant';
        }

        return 'global';
    }
}
