<?php

namespace App\Http\Resources\Admin;

use App\Services\Payment\SettlementService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * 结算单 API Resource（Admin）
 *
 * @mixin \App\Models\Central\SettlementRecord
 */
class SettlementRecordResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id'                => $this->id,
            'merchant_id'       => $this->merchant_id,
            'settlement_no'     => $this->settlement_no,
            'total_amount'      => $this->total_amount,
            'commission_amount' => $this->commission_amount,
            'net_amount'        => $this->net_amount,
            'order_count'       => $this->order_count,
            'period_start'      => $this->period_start?->toDateString(),
            'period_end'        => $this->period_end?->toDateString(),
            'status'            => $this->status,
            'status_label'      => SettlementService::STATUS_LABELS[$this->status] ?? 'unknown',
            'reviewed_by'       => $this->reviewed_by,
            'reviewed_at'       => $this->reviewed_at?->toIso8601String(),
            'transaction_ref'   => $this->transaction_ref,
            'remark'            => $this->remark,
            'settled_at'        => $this->settled_at?->toIso8601String(),
            'created_at'        => $this->created_at?->toIso8601String(),
            'updated_at'        => $this->updated_at?->toIso8601String(),

            // 关联：商户基本信息（仅在预加载时包含）
            'merchant' => $this->whenLoaded('merchant', fn () => [
                'id'            => $this->merchant->id,
                'merchant_name' => $this->merchant->merchant_name,
                'email'         => $this->merchant->email,
            ]),

            // 关联：结算明细（仅在预加载时包含）
            'details' => SettlementDetailResource::collection($this->whenLoaded('details')),
        ];
    }
}
