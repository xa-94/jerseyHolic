<?php

namespace App\Http\Resources\Admin;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * 安全描述 API Resource（Admin 端）
 *
 * 用于格式化 PaypalSafeDescription 模型输出。
 */
class SafeDescriptionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                 => $this->id,
            'store_id'           => $this->store_id,
            'store_name'         => $this->whenLoaded('store', fn () => $this->store?->store_name),
            'product_category'   => $this->product_category,
            'safe_name'          => $this->safe_name,
            'safe_description'   => $this->safe_description,
            'safe_category_code' => $this->safe_category_code,
            'weight'             => $this->weight,
            'status'             => $this->status,
            'status_label'       => $this->status === 1 ? '启用' : '禁用',
            'created_at'         => $this->created_at?->format('Y-m-d H:i:s'),
            'updated_at'         => $this->updated_at?->format('Y-m-d H:i:s'),
        ];
    }
}
