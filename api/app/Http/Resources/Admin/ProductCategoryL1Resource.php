<?php

declare(strict_types=1);

namespace App\Http\Resources\Admin;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * 一级品类 L1 API Resource（Admin 端）
 *
 * 用于格式化 ProductCategoryL1 模型输出，支持嵌套子品类。
 */
class ProductCategoryL1Resource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'              => $this->id,
            'code'            => $this->code,
            'name'            => $this->name,
            'icon'            => $this->icon,
            'is_sensitive'    => $this->is_sensitive,
            'sensitive_ratio' => $this->sensitive_ratio,
            'sort_order'      => $this->sort_order,
            'status'          => $this->status,
            'status_label'    => $this->status === 1 ? '启用' : '禁用',
            'children_count'  => $this->whenCounted('children'),
            'children'        => ProductCategoryL2Resource::collection($this->whenLoaded('children')),
            'created_at'      => $this->created_at?->format('Y-m-d H:i:s'),
            'updated_at'      => $this->updated_at?->format('Y-m-d H:i:s'),
        ];
    }
}
