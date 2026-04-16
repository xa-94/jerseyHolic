<?php

namespace App\Http\Resources\Admin;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * 商户-支付分组映射 API 资源
 *
 * 用于 Admin 端映射列表和详情输出。
 */
class PaymentGroupMappingResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                 => $this->id,
            'merchant_id'        => (int) $this->merchant_id,
            'pay_method'         => $this->pay_method,
            'payment_group_id'   => (int) $this->payment_group_id,
            'priority'           => (int) $this->priority,
            'created_at'         => $this->created_at?->toISOString(),
            'updated_at'         => $this->updated_at?->toISOString(),

            // 关联的支付分组信息
            'payment_group'      => new PaymentAccountGroupResource(
                $this->whenLoaded('paymentGroup')
            ),
        ];
    }
}
