<?php

namespace App\Http\Resources\Admin;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * 黑名单条目 API 资源
 *
 * 用于 Admin 端黑名单列表和详情输出。
 */
class BlacklistResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'          => $this->id,
            'scope'       => $this->scope,
            'merchant_id' => $this->merchant_id,
            'dimension'   => $this->dimension,
            'value'       => $this->value,
            'reason'      => $this->reason,
            'is_expired'  => $this->isExpired(),

            // 关联商户
            'merchant' => $this->when(
                $this->relationLoaded('merchant'),
                fn () => $this->merchant ? [
                    'id'   => $this->merchant->id,
                    'name' => $this->merchant->merchant_name,
                ] : null
            ),

            'expires_at'  => $this->expires_at?->toISOString(),
            'created_at'  => $this->created_at?->toISOString(),
            'updated_at'  => $this->updated_at?->toISOString(),
        ];
    }
}
