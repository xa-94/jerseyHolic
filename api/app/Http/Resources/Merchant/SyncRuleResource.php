<?php

declare(strict_types=1);

namespace App\Http\Resources\Merchant;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * 同步规则 API 资源
 *
 * 用于详情和列表项的统一输出格式。
 */
class SyncRuleResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id'                => $this->id,
            'name'              => $this->name,
            'target_store_ids'  => $this->target_store_ids,
            'excluded_store_ids' => $this->excluded_store_ids,
            'sync_fields'       => $this->sync_fields,
            'price_strategy'    => $this->price_strategy,
            'price_multiplier'  => $this->price_multiplier,
            'auto_sync'         => $this->auto_sync,
            'status'            => $this->status,
            'last_synced_at'    => $this->last_synced_at?->toIso8601String(),
            'created_at'        => $this->created_at?->toIso8601String(),
            'updated_at'        => $this->updated_at?->toIso8601String(),
        ];
    }
}
