<?php

namespace App\Http\Resources\Admin;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * 商户风险评分 API 资源
 *
 * 用于 Admin 端风险评分详情输出。
 * 支持 RiskScoreResult DTO 和 MerchantRiskScore Model 两种数据源。
 */
class RiskScoreResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        // 支持 DTO（数组形式）
        if (is_array($this->resource)) {
            return $this->resource;
        }

        // 支持 MerchantRiskScore Model
        return [
            'merchant_id'  => $this->merchant_id,
            'total_score'  => (int) $this->score,
            'level'        => $this->level,
            'dimensions'   => $this->factors,
            'evaluated_at' => $this->evaluated_at?->toISOString(),

            // 关联商户
            'merchant' => $this->when(
                $this->relationLoaded('merchant'),
                fn () => $this->merchant ? [
                    'id'     => $this->merchant->id,
                    'name'   => $this->merchant->merchant_name,
                    'status' => $this->merchant->status,
                    'level'  => $this->merchant->level,
                ] : null
            ),

            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
