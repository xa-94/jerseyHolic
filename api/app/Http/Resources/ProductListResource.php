<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * 商品列表简化资源
 * 用于列表页（后台 + 买家端）
 */
class ProductListResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        // 主图
        $mainImage = $this->whenLoaded('images', function () {
            $main = $this->images->where('is_main', 1)->first();
            return $main ? $main->image : ($this->images->first()?->image);
        }, $this->image);

        // 当前语言描述
        $description = $this->whenLoaded('descriptions', function () {
            return $this->descriptions->first();
        });

        return [
            'id'             => $this->id,
            'sku'            => $this->sku,
            'model'          => $this->model,
            'image'          => $mainImage,
            'name'           => $description?->name ?? '',
            'slug'           => $description?->slug ?? '',
            'price'          => (string)$this->price,
            'special_price'  => $this->hasActiveSpecialPrice()
                ? (string)$this->special_price
                : null,
            'effective_price' => (string)$this->effective_price,
            'quantity'       => $this->quantity,
            'stock_status'   => $this->stock_status,
            'status'         => $this->status instanceof \App\Enums\ProductStatus
                ? $this->status->value
                : $this->status,
            'status_label'   => $this->status instanceof \App\Enums\ProductStatus
                ? $this->status->label()
                : '',
            'is_featured'    => (bool)$this->is_featured,
            'sku_prefix'     => $this->sku_prefix,
            'sold'           => $this->sold ?? 0,
            'sort_order'     => $this->sort_order,
            'created_at'     => $this->created_at?->toISOString(),

            // 后台安全映射信息（通过 setAttribute 注入）
            'safe_name'      => $this->when(
                $this->getAttribute('safe_name') !== null,
                $this->getAttribute('safe_name')
            ),
            'should_replace' => $this->when(
                $this->getAttribute('should_replace') !== null,
                (bool)$this->getAttribute('should_replace')
            ),

            // 关联统计
            'skus_count'     => $this->whenCounted('skus'),
        ];
    }

    private function hasActiveSpecialPrice(): bool
    {
        return $this->special_price
            && $this->special_start_at
            && $this->special_start_at <= now()
            && ($this->special_end_at === null || $this->special_end_at >= now());
    }
}
