<?php

declare(strict_types=1);

namespace App\Http\Resources\Api;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * 买家端商品列表 Resource（M4-011 / F-PROD-042）
 *
 * 列表页简化版，仅输出必要字段。
 * 支持 cloak_mode：safe 模式下自动替换名称和图片。
 *
 * 字段：id, slug, name, price, effective_price, image, category_name, stock_status
 */
class ProductListResource extends JsonResource
{
    /**
     * 外部注入的 cloak_mode
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

        // Product 模型
        return $this->formatFromModel($request);
    }

    /* ----------------------------------------------------------------
     |  从数组格式化
     | ---------------------------------------------------------------- */

    private function formatFromArray(array $data): array
    {
        return [
            'id'              => $data['id'] ?? null,
            'slug'            => $data['slug'] ?? '',
            'name'            => $data['name'] ?? '',
            'price'           => $data['price'] ?? '0.00',
            'effective_price' => $data['effective_price'] ?? '0.00',
            'special_price'   => $data['special_price'] ?? null,
            'image'           => $data['image'] ?? null,
            'category_name'   => $data['category']['name'] ?? '',
            'stock_status'    => $data['stock_status'] ?? 0,
            'is_featured'     => $data['is_featured'] ?? false,
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
     * 真实模式
     */
    private function formatReal(): array
    {
        $description = $this->getLocalizedDescription();
        $mainImage = $this->getMainImage();

        return [
            'id'              => $this->id,
            'slug'            => $description?->slug ?? '',
            'name'            => $description?->name ?? '',
            'price'           => (string) $this->price,
            'effective_price' => (string) $this->effective_price,
            'special_price'   => $this->hasActiveSpecialPrice() ? (string) $this->special_price : null,
            'image'           => $mainImage,
            'category_name'   => $this->getPrimaryCategoryName(),
            'stock_status'    => $this->stock_status,
            'is_featured'     => (bool) $this->is_featured,
        ];
    }

    /**
     * 安全模式
     */
    private function formatSafe(): array
    {
        $safeName = $this->getAttribute('safe_name') ?? 'General Merchandise';

        return [
            'id'              => $this->id,
            'slug'            => $this->id . '-' . \Illuminate\Support\Str::slug($safeName),
            'name'            => $safeName,
            'price'           => (string) $this->price,
            'effective_price' => (string) $this->effective_price,
            'special_price'   => $this->hasActiveSpecialPrice() ? (string) $this->special_price : null,
            'image'           => $this->getAttribute('safe_image') ?? $this->getMainImage(),
            'category_name'   => $this->getPrimaryCategoryName(),
            'stock_status'    => $this->stock_status,
            'is_featured'     => (bool) $this->is_featured,
        ];
    }

    /* ----------------------------------------------------------------
     |  辅助方法
     | ---------------------------------------------------------------- */

    private function resolveCloakMode(Request $request): ?string
    {
        if ($this->cloakMode !== null) {
            return $this->cloakMode;
        }

        if (isset($this->additional['cloak_mode'])) {
            return $this->additional['cloak_mode'];
        }

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

    private function getMainImage(): ?string
    {
        if ($this->resource->relationLoaded('images')) {
            $main = $this->resource->images->where('is_main', 1)->first();
            return $main ? $main->image : ($this->resource->images->first()?->image ?? $this->image);
        }

        return $this->image;
    }

    private function getPrimaryCategoryName(): string
    {
        if (!$this->resource->relationLoaded('categories')) {
            return '';
        }

        $primary = $this->resource->categories->firstWhere('pivot.is_primary', 1)
            ?? $this->resource->categories->first();

        if ($primary === null) {
            return '';
        }

        if ($primary->relationLoaded('descriptions')) {
            $locale = app()->getLocale();
            $desc = $primary->descriptions->firstWhere('locale', $locale)
                ?? $primary->descriptions->firstWhere('locale', 'en')
                ?? $primary->descriptions->first();
            return $desc?->name ?? '';
        }

        return '';
    }

    private function hasActiveSpecialPrice(): bool
    {
        return $this->special_price
            && $this->special_start_at
            && $this->special_start_at <= now()
            && ($this->special_end_at === null || $this->special_end_at >= now());
    }
}
