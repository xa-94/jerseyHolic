<?php

declare(strict_types=1);

namespace App\Http\Resources\Merchant;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * 站点商品配置 API 资源
 *
 * 输出站点级的商品展示差异化配置。
 */
class StoreProductConfigResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'store_id'                   => $this->store_id,
            'store_name'                 => $this->whenLoaded('store', fn () => $this->store->store_name),
            'price_override_enabled'     => $this->price_override_enabled,
            'price_override_strategy'    => $this->price_override_strategy,
            'price_override_value'       => $this->price_override_value,
            'safe_name_override_enabled' => $this->safe_name_override_enabled,
            'custom_placeholder_image'   => $this->custom_placeholder_image,
            'display_currency'           => $this->display_currency,
            'auto_translate'             => $this->auto_translate,
            'default_language'           => $this->default_language,
            'updated_at'                 => $this->updated_at?->toIso8601String(),
        ];
    }
}
