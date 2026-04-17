<?php

declare(strict_types=1);

namespace App\Http\Resources\Admin;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * 二级品类 L2 API Resource（Admin 端）
 *
 * 用于格式化 ProductCategoryL2 模型输出。
 */
class ProductCategoryL2Resource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'            => $this->id,
            'l1_id'         => $this->l1_id,
            'code'          => $this->code,
            'name'          => $this->name,
            'is_sensitive'  => $this->is_sensitive,
            'sort_order'    => $this->sort_order,
            'status'        => $this->status,
            'status_label'  => $this->status === 1 ? '启用' : '禁用',
            'parent'        => new ProductCategoryL1Resource($this->whenLoaded('parent')),
            'created_at'    => $this->created_at?->format('Y-m-d H:i:s'),
            'updated_at'    => $this->updated_at?->format('Y-m-d H:i:s'),
        ];
    }
}
