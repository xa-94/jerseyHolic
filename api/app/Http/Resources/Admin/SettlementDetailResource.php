<?php

namespace App\Http\Resources\Admin;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * 结算明细 API Resource（Admin）
 *
 * @mixin \App\Models\Central\SettlementDetail
 */
class SettlementDetailResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id'                => $this->id,
            'settlement_id'     => $this->settlement_id,
            'store_id'          => $this->store_id,
            'order_count'       => $this->order_count,
            'total_amount'      => $this->total_amount,
            'commission_amount' => $this->commission_amount,
            'net_amount'        => $this->net_amount,
            'currency'          => $this->currency,
            'created_at'        => $this->created_at?->toIso8601String(),
            'updated_at'        => $this->updated_at?->toIso8601String(),

            // 关联：店铺基本信息（仅在预加载时包含）
            'store' => $this->whenLoaded('store', fn () => [
                'id'         => $this->store->id,
                'store_name' => $this->store->store_name,
                'store_code' => $this->store->store_code,
            ]),
        ];
    }
}
