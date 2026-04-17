<?php

declare(strict_types=1);

namespace App\Http\Resources\Admin;

use App\Models\Central\CategorySafeName;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * 品类安全映射名称 API Resource（Admin 端 / M4-002）
 *
 * 用于格式化 CategorySafeName 模型输出。
 */
class CategorySafeNameResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        /** @var CategorySafeName $this */
        $data = [
            'id'              => $this->id,
            'category_l1_id'  => $this->category_l1_id,
            'category_l1_name' => $this->whenLoaded('categoryL1', fn () => $this->categoryL1?->getLocalizedName('en')),
            'category_l2_id'  => $this->category_l2_id,
            'category_l2_name' => $this->whenLoaded('categoryL2', fn () => $this->categoryL2?->getLocalizedName('en')),
            'sku_prefix'      => $this->sku_prefix,
            'store_id'        => $this->store_id,
            'store_name'      => $this->whenLoaded('store', fn () => $this->store?->store_name),
            'weight'          => $this->weight,
            'status'          => $this->status,
            'status_label'    => $this->status === CategorySafeName::STATUS_ACTIVE ? '启用' : '禁用',
            'created_at'      => $this->created_at?->format('Y-m-d H:i:s'),
            'updated_at'      => $this->updated_at?->format('Y-m-d H:i:s'),
        ];

        // 添加 16 语言安全名称
        foreach (CategorySafeName::SUPPORTED_LOCALES as $locale) {
            $field = "safe_name_{$locale}";
            $data[$field] = $this->{$field};
        }

        return $data;
    }
}
