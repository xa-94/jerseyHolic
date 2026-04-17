<?php

declare(strict_types=1);

namespace App\Http\Resources\Admin;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * 敏感品牌 API 资源（Admin 端）
 *
 * 用于敏感品牌黑名单列表和详情输出。
 */
class SensitiveBrandResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'             => $this->id,
            'brand_name'     => $this->brand_name,
            'brand_aliases'  => $this->brand_aliases ?? [],
            'category_l1_id' => $this->category_l1_id,
            'risk_level'     => $this->risk_level,
            'reason'         => $this->reason,
            'status'         => $this->status,

            // 关联品类
            'category_l1' => $this->when(
                $this->relationLoaded('categoryL1'),
                fn () => $this->categoryL1 ? [
                    'id'   => $this->categoryL1->id,
                    'code' => $this->categoryL1->code,
                    'name' => $this->categoryL1->getLocalizedName(),
                ] : null
            ),

            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
