<?php

declare(strict_types=1);

namespace App\Http\Resources\Admin;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * 退款影响汇总 API Resource（Admin）
 *
 * 返回商户退款影响的汇总数据。
 */
class RefundImpactResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'merchant_id'        => $this->merchant_id,
            'total_refunded'     => $this->total_refunded,
            'pending_deduction'  => $this->pending_deduction,
            'applied_deduction'  => $this->applied_deduction,
        ];
    }
}
