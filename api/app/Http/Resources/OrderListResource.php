<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * 订单列表简要资源
 * 用于列表页（减少数据传输量）
 */
class OrderListResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'       => $this->id,
            'order_no' => $this->order_no,
            'domain'   => $this->domain,
            'currency' => $this->currency,
            'total'    => (string)$this->total,

            // 支付状态
            'pay_status'       => $this->pay_status instanceof \App\Enums\OrderPaymentStatus
                ? $this->pay_status->value : $this->pay_status,
            'pay_status_label' => $this->pay_status instanceof \App\Enums\OrderPaymentStatus
                ? $this->pay_status->label() : '',
            'pay_type'         => $this->pay_type instanceof \App\Enums\PaymentChannel
                ? $this->pay_type->value : $this->pay_type,
            'pay_time'         => $this->pay_time?->toISOString(),

            // 发货状态
            'shipment_status'       => $this->shipment_status instanceof \App\Enums\OrderShippingStatus
                ? $this->shipment_status->value : $this->shipment_status,
            'shipment_status_label' => $this->shipment_status instanceof \App\Enums\OrderShippingStatus
                ? $this->shipment_status->label() : '',

            // 退款状态
            'refund_status'       => $this->refund_status instanceof \App\Enums\OrderRefundStatus
                ? $this->refund_status->value : $this->refund_status,
            'refund_status_label' => $this->refund_status instanceof \App\Enums\OrderRefundStatus
                ? $this->refund_status->label() : '',

            // 客户基本信息
            'customer_id'    => $this->customer_id,
            'customer_email' => $this->customer_email,
            'customer_name'  => $this->customer_name,

            // SKU 类型标记
            'is_zw'   => (bool)$this->is_zw,
            'is_diy'  => (bool)$this->is_diy,
            'is_wpz'  => (bool)$this->is_wpz,

            'created_at' => $this->created_at?->toISOString(),

            // 商品摘要（已加载时）
            'items_count' => $this->whenLoaded('items', fn() => $this->items->count()),
            'items_summary' => $this->whenLoaded('items', function () {
                return $this->items->map(fn($item) => [
                    'name'     => $item->name,
                    'quantity' => $item->quantity,
                    'price'    => (string)$item->price,
                ]);
            }),

            // 收货地址摘要（已加载时）
            'shipping_address' => $this->whenLoaded('shippingAddress', fn() => $this->shippingAddress ? [
                'full_name'    => $this->shippingAddress->full_name,
                'address_1'    => $this->shippingAddress->address_1,
                'city'         => $this->shippingAddress->city,
                'country_code' => $this->shippingAddress->country_code,
                'postcode'     => $this->shippingAddress->postcode,
            ] : null),

            // 客户关联（已加载时）
            'customer' => $this->whenLoaded('customer', fn() => $this->customer ? [
                'id'    => $this->customer->id,
                'name'  => $this->customer->name ?? null,
                'email' => $this->customer->email ?? null,
            ] : null),
        ];
    }
}
