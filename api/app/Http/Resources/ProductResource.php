<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * 商品完整详情资源
 * 用于详情页（后台 + 买家端）
 */
class ProductResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                => $this->id,
            'sku'               => $this->sku,
            'sku_prefix'        => $this->sku_prefix,
            'model'             => $this->model,
            'image'             => $this->image,
            'price'             => (string)$this->price,
            'cost_price'        => (string)($this->cost_price ?? '0.00'),
            'special_price'     => $this->special_price ? (string)$this->special_price : null,
            'special_start_at'  => $this->special_start_at?->toISOString(),
            'special_end_at'    => $this->special_end_at?->toISOString(),
            'effective_price'   => (string)$this->effective_price,
            'quantity'          => $this->quantity,
            'stock_status'      => $this->stock_status,
            'subtract_stock'    => $this->subtract_stock,
            'weight'            => (string)($this->weight ?? '0.00'),
            'length'            => $this->length,
            'width'             => $this->width,
            'height'            => $this->height,
            'minimum'           => $this->minimum ?? 1,
            'sort_order'        => $this->sort_order ?? 0,
            'status'            => $this->status instanceof \App\Enums\ProductStatus
                ? $this->status->value
                : $this->status,
            'status_label'      => $this->status instanceof \App\Enums\ProductStatus
                ? $this->status->label()
                : '',
            'is_featured'       => (bool)$this->is_featured,
            'requires_shipping' => (bool)$this->requires_shipping,
            'merchant_id'       => $this->merchant_id,
            'viewed'            => $this->viewed ?? 0,
            'sold'              => $this->sold ?? 0,
            'upc'               => $this->upc,
            'ean'               => $this->ean,
            'isbn'              => $this->isbn,
            'mpn'               => $this->mpn,
            'created_at'        => $this->created_at?->toISOString(),
            'updated_at'        => $this->updated_at?->toISOString(),

            // 多语言描述
            'descriptions' => $this->whenLoaded('descriptions', function () {
                return $this->descriptions->map(fn($d) => [
                    'locale'            => $d->locale,
                    'name'              => $d->name,
                    'description'       => $d->description,
                    'short_description' => $d->short_description,
                    'meta_title'        => $d->meta_title,
                    'meta_description'  => $d->meta_description,
                    'meta_keywords'     => $d->meta_keywords,
                    'slug'              => $d->slug,
                    'tag'               => $d->tag,
                ]);
            }),

            // 单语言描述（买家端，当只加载了一种语言时）
            'name'              => $this->whenLoaded('descriptions', function () {
                return $this->descriptions->first()?->name ?? '';
            }),
            'description'       => $this->whenLoaded('descriptions', function () {
                return $this->descriptions->first()?->description ?? '';
            }),
            'short_description' => $this->whenLoaded('descriptions', function () {
                return $this->descriptions->first()?->short_description ?? '';
            }),
            'slug'              => $this->whenLoaded('descriptions', function () {
                return $this->descriptions->first()?->slug ?? '';
            }),

            // 图片列表
            'images' => $this->whenLoaded('images', function () {
                return $this->images->map(fn($img) => [
                    'id'         => $img->id,
                    'image'      => $img->image,
                    'is_main'    => (bool)$img->is_main,
                    'sort_order' => $img->sort_order,
                ]);
            }),

            // SKU 变体
            'skus' => $this->whenLoaded('skus', function () {
                return $this->skus->map(fn($sku) => [
                    'id'           => $sku->id,
                    'sku'          => $sku->sku,
                    'price'        => (string)$sku->price,
                    'cost_price'   => (string)($sku->cost_price ?? '0.00'),
                    'quantity'     => $sku->quantity,
                    'weight'       => (string)($sku->weight ?? '0.00'),
                    'image'        => $sku->image,
                    'option_values' => $sku->option_values ?? [],
                    'status'       => $sku->status,
                    'sort_order'   => $sku->sort_order,
                ]);
            }),

            // 属性值
            'attributes' => $this->whenLoaded('attributeValues', function () {
                return $this->attributeValues->map(fn($av) => [
                    'attribute_id'   => $av->attribute_id,
                    'attribute_name' => $av->relationLoaded('attribute') ? $av->attribute?->name : null,
                    'value'          => $av->value,
                    'locale'         => $av->locale,
                ]);
            }),

            // 分类
            'categories' => $this->whenLoaded('categories', function () {
                return $this->categories->map(fn($cat) => [
                    'id'     => $cat->id,
                    'name'   => $cat->relationLoaded('descriptions')
                        ? ($cat->descriptions->first()?->name ?? '')
                        : '',
                    'status' => $cat->status,
                ]);
            }),

            // 相关商品（买家端）
            'related_products' => $this->when(
                $this->getAttribute('related_products') !== null,
                fn() => ProductListResource::collection($this->getAttribute('related_products'))
            ),

            // 后台安全映射信息
            'safe_name'        => $this->when(
                $this->getAttribute('safe_name') !== null,
                $this->getAttribute('safe_name')
            ),
            'safe_description' => $this->when(
                $this->getAttribute('safe_description') !== null,
                $this->getAttribute('safe_description')
            ),
            'should_replace'   => $this->when(
                $this->getAttribute('should_replace') !== null,
                (bool)$this->getAttribute('should_replace')
            ),

            // 精确映射详情（后台用）
            'safe_mapping' => $this->whenLoaded('safeMapping', function () {
                $mapping = $this->safeMapping;
                if (!$mapping) return null;
                return [
                    'id'              => $mapping->id,
                    'mapping_type'    => $mapping->mapping_type,
                    'safe_product_id' => $mapping->safe_product_id,
                    'safe_product'    => $mapping->relationLoaded('safeProduct') && $mapping->safeProduct
                        ? ['id' => $mapping->safeProduct->id, 'name' => $mapping->safeProduct->name]
                        : null,
                ];
            }),
        ];
    }
}
