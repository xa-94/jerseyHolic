<?php

declare(strict_types=1);

namespace App\Http\Resources\Api;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * 买家端商品详情 Resource（M4-011 / F-PROD-041）
 *
 * 根据 cloak_mode 决定输出真实数据或安全脱敏数据。
 * 当请求中携带 cloak_mode = 'safe' 时，自动切换为安全内容展示。
 *
 * 使用方式：
 *  - 传入 ProductDisplayService 返回的展示数据数组
 *  - 或传入 Product 模型（需配合 additional 传递 cloak_mode）
 *
 * 示例：
 *  return new ProductDisplayResource($displayData);
 *  return (new ProductDisplayResource($product))->additional(['cloak_mode' => 'safe']);
 */
class ProductDisplayResource extends JsonResource
{
    /**
     * 外部注入的 cloak_mode（优先于 request）
     */
    private ?string $cloakMode = null;

    /**
     * 设置 cloak_mode
     */
    public function withCloakMode(?string $mode): static
    {
        $this->cloakMode = $mode;
        return $this;
    }

    public function toArray(Request $request): array
    {
        // 如果 resource 本身就是数组（来自 ProductDisplayService）
        if (is_array($this->resource)) {
            return $this->formatFromArray($this->resource);
        }

        // 如果 resource 是 Product 模型
        return $this->formatFromModel($request);
    }

    /* ----------------------------------------------------------------
     |  从数组格式化（ProductDisplayService 返回值）
     | ---------------------------------------------------------------- */

    private function formatFromArray(array $data): array
    {
        return [
            'id'                => $data['id'] ?? null,
            'sku'               => $data['sku'] ?? null,
            'slug'              => $data['slug'] ?? '',
            'name'              => $data['name'] ?? '',
            'description'       => $data['description'] ?? '',
            'short_description' => $data['short_description'] ?? '',
            'price'             => $data['price'] ?? '0.00',
            'effective_price'   => $data['effective_price'] ?? '0.00',
            'special_price'     => $data['special_price'] ?? null,
            'currency'          => $data['currency'] ?? 'USD',
            'image'             => $data['image'] ?? null,
            'images'            => $data['images'] ?? [],
            'category'          => $data['category'] ?? null,
            'attributes'        => $data['attributes'] ?? [],
            'variants'          => $data['variants'] ?? [],
            'stock_status'      => $data['stock_status'] ?? 0,
            'is_featured'       => $data['is_featured'] ?? false,
        ];
    }

    /* ----------------------------------------------------------------
     |  从 Product 模型格式化
     | ---------------------------------------------------------------- */

    private function formatFromModel(Request $request): array
    {
        $cloakMode = $this->resolveCloakMode($request);

        if ($cloakMode === 'safe') {
            return $this->formatSafe();
        }

        return $this->formatReal();
    }

    /**
     * 真实模式输出
     */
    private function formatReal(): array
    {
        $description = $this->getLocalizedDescription();

        return [
            'id'                => $this->id,
            'sku'               => $this->sku,
            'slug'              => $description?->slug ?? '',
            'name'              => $description?->name ?? '',
            'description'       => $description?->description ?? '',
            'short_description' => $description?->short_description ?? '',
            'price'             => (string) $this->price,
            'effective_price'   => (string) $this->effective_price,
            'special_price'     => $this->special_price ? (string) $this->special_price : null,
            'currency'          => 'USD',
            'image'             => $this->image,
            'images'            => $this->formatImages(),
            'category'          => $this->formatCategory(),
            'attributes'        => $this->formatAttributes(),
            'variants'          => $this->formatVariants(),
            'stock_status'      => $this->stock_status,
            'is_featured'       => (bool) $this->is_featured,
        ];
    }

    /**
     * 安全模式输出（使用 setAttribute 注入的安全数据）
     */
    private function formatSafe(): array
    {
        $safeName = $this->getAttribute('safe_name') ?? $this->getAttribute('name') ?? 'General Merchandise';

        return [
            'id'                => $this->id,
            'sku'               => $this->sku,
            'slug'              => $this->id . '-' . \Illuminate\Support\Str::slug($safeName),
            'name'              => $safeName,
            'description'       => $this->getAttribute('safe_description')
                ?? "High quality {$safeName}. Comfortable fabric, excellent craftsmanship.",
            'short_description' => $safeName,
            'price'             => (string) $this->price,
            'effective_price'   => (string) $this->effective_price,
            'special_price'     => $this->special_price ? (string) $this->special_price : null,
            'currency'          => 'USD',
            'image'             => $this->getAttribute('safe_image') ?? $this->image,
            'images'            => $this->getAttribute('safe_images') ?? $this->formatImages(),
            'category'          => $this->formatCategory(),
            'attributes'        => $this->getAttribute('safe_attributes') ?? [],
            'variants'          => $this->formatVariants(),
            'stock_status'      => $this->stock_status,
            'is_featured'       => (bool) $this->is_featured,
        ];
    }

    /* ----------------------------------------------------------------
     |  辅助方法
     | ---------------------------------------------------------------- */

    private function resolveCloakMode(Request $request): ?string
    {
        // 1. 方法注入
        if ($this->cloakMode !== null) {
            return $this->cloakMode;
        }

        // 2. additional 数据
        if (isset($this->additional['cloak_mode'])) {
            return $this->additional['cloak_mode'];
        }

        // 3. request attributes（由 CloakContentFilter 中间件设置）
        return $request->attributes->get('cloak_mode');
    }

    private function getLocalizedDescription(): ?object
    {
        if (!$this->resource->relationLoaded('descriptions')) {
            return null;
        }

        $locale = app()->getLocale();

        return $this->resource->descriptions->firstWhere('locale', $locale)
            ?? $this->resource->descriptions->firstWhere('locale', 'en')
            ?? $this->resource->descriptions->first();
    }

    private function formatImages(): array
    {
        if (!$this->resource->relationLoaded('images')) {
            return $this->image ? [['image' => $this->image, 'is_main' => true, 'sort_order' => 0]] : [];
        }

        return $this->resource->images->map(fn ($img) => [
            'image'      => $img->image,
            'is_main'    => (bool) $img->is_main,
            'sort_order' => $img->sort_order,
        ])->all();
    }

    private function formatCategory(): ?array
    {
        if (!$this->resource->relationLoaded('categories')) {
            return null;
        }

        $primary = $this->resource->categories->firstWhere('pivot.is_primary', 1)
            ?? $this->resource->categories->first();

        if ($primary === null) {
            return null;
        }

        $name = '';
        if ($primary->relationLoaded('descriptions')) {
            $locale = app()->getLocale();
            $desc = $primary->descriptions->firstWhere('locale', $locale)
                ?? $primary->descriptions->firstWhere('locale', 'en')
                ?? $primary->descriptions->first();
            $name = $desc?->name ?? '';
        }

        return ['id' => $primary->id, 'name' => $name];
    }

    private function formatAttributes(): array
    {
        if (!$this->resource->relationLoaded('attributeValues')) {
            return [];
        }

        return $this->resource->attributeValues->map(fn ($av) => [
            'attribute_id'   => $av->attribute_id,
            'attribute_name' => $av->relationLoaded('attribute') ? $av->attribute?->name : null,
            'value'          => $av->value,
        ])->all();
    }

    private function formatVariants(): array
    {
        if (!$this->resource->relationLoaded('skus')) {
            return [];
        }

        return $this->resource->skus->map(fn ($sku) => [
            'id'            => $sku->id,
            'sku'           => $sku->sku,
            'price'         => (string) $sku->price,
            'quantity'      => $sku->quantity,
            'image'         => $sku->image,
            'option_values' => $sku->option_values ?? [],
        ])->all();
    }
}
