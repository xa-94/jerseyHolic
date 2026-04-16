<?php

namespace App\Http\Resources\Admin;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * 支付账号分组 API 资源
 *
 * 用于 Admin 端分组列表和详情输出。
 */
class PaymentAccountGroupResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                 => $this->id,
            'name'               => $this->name,
            'type'               => $this->type,
            'group_type'         => $this->group_type,
            'description'        => $this->description,
            'is_blacklist_group' => (int) $this->is_blacklist_group,
            'status'             => (int) $this->status,
            'account_count'      => $this->when(
                $this->payment_accounts_count !== null,
                $this->payment_accounts_count
            ),
            'created_at'         => $this->created_at?->toISOString(),
            'updated_at'         => $this->updated_at?->toISOString(),

            // 详情模式时加载关联账号
            'payment_accounts'   => PaymentAccountResource::collection(
                $this->whenLoaded('paymentAccounts')
            ),
        ];
    }
}
