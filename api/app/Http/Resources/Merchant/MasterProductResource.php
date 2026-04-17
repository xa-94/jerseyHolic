<?php

declare(strict_types=1);

namespace App\Http\Resources\Merchant;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * 主商品 API 资源
 *
 * 用于详情和列表项的统一输出格式。
 * 包含基础字段 + 嵌套的 translations 数组。
 */
class MasterProductResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id'             => $this->id,
            'sku'            => $this->sku,
            'name'           => $this->name,
            'description'    => $this->description,
            'category_l1_id' => $this->category_l1_id,
            'category_l2_id' => $this->category_l2_id,
            'is_sensitive'   => $this->is_sensitive,
            'base_price'     => $this->base_price,
            'currency'       => $this->currency,
            'images'         => $this->images,
            'attributes'     => $this->attributes,
            'variants'       => $this->variants,
            'weight'         => $this->weight,
            'dimensions'     => $this->dimensions,
            'status'         => $this->status,
            'sync_status'    => $this->sync_status,
            'last_synced_at' => $this->last_synced_at?->toIso8601String(),
            'translations'   => $this->whenLoaded('translations', function () {
                return $this->translations->map(fn ($t) => [
                    'id'               => $t->id,
                    'locale'           => $t->locale,
                    'name'             => $t->name,
                    'description'      => $t->description,
                    'meta_title'       => $t->meta_title,
                    'meta_description' => $t->meta_description,
                ]);
            }),
            'created_at'     => $this->created_at?->toIso8601String(),
            'updated_at'     => $this->updated_at?->toIso8601String(),
        ];
    }
}
