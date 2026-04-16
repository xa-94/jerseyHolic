<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * 订单完整详情资源（含所有关联数据）
 * 用于详情页（后台 + 买家端）
 */
class OrderResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'               => $this->id,
            'order_no'         => $this->order_no,
            'a_order_no'       => $this->a_order_no,
            'domain'           => $this->domain,
            'currency'         => $this->currency,
            'exchange_rate'    => (string)$this->exchange_rate,
            'price'            => (string)$this->price,
            'shipping_fee'     => (string)$this->shipping_fee,
            'tax_amount'       => (string)$this->tax_amount,
            'discount_amount'  => (string)$this->discount_amount,
            'total'            => (string)$this->total,

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

            // 客户信息
            'customer_id'    => $this->customer_id,
            'customer_email' => $this->customer_email,
            'customer_name'  => $this->customer_name,
            'customer_phone' => $this->customer_phone,

            // SKU 分类标记
            'is_zw'          => (bool)$this->is_zw,
            'is_diy'         => (bool)$this->is_diy,
            'is_wpz'         => (bool)$this->is_wpz,
            'is_blacklist'   => (bool)$this->is_blacklist,

            // PayPal/Stripe 信息（后台）
            'paypal_email'          => $this->paypal_email,
            'paypal_order_id'       => $this->paypal_order_id,
            'paypal_transaction_no' => $this->paypal_transaction_no,
            'stripe_session_id'     => $this->stripe_session_id,

            // 优惠码 / 备注
            'coupon_code'    => $this->coupon_code,
            'remark'         => $this->remark,

            'created_at'     => $this->created_at?->toISOString(),
            'updated_at'     => $this->updated_at?->toISOString(),

            // 订单商品项
            'items' => $this->whenLoaded('items', function () {
                return $this->items->map(fn($item) => [
                    'id'             => $item->id,
                    'product_id'     => $item->product_id,
                    'product_sku_id' => $item->product_sku_id,
                    'sku'            => $item->sku,
                    'name'           => $item->name,
                    'safe_name'      => $item->safe_name,
                    'image'          => $item->image,
                    'quantity'       => $item->quantity,
                    'price'          => (string)$item->price,
                    'total'          => (string)$item->total,
                    'weight'         => (string)($item->weight ?? '0.00'),
                    'options'        => $item->options ?? [],
                ]);
            }),

            // 地址
            'addresses' => $this->whenLoaded('addresses', function () {
                return $this->addresses->map(fn($addr) => $this->formatAddress($addr));
            }),

            // 金额明细
            'totals' => $this->whenLoaded('totals', function () {
                return $this->totals->sortBy('sort_order')->map(fn($t) => [
                    'code'       => $t->code,
                    'title'      => $t->title,
                    'value'      => (string)$t->value,
                    'sort_order' => $t->sort_order,
                ])->values();
            }),

            // 操作历史
            'histories' => $this->whenLoaded('histories', function () {
                return $this->histories->map(fn($h) => [
                    'id'              => $h->id,
                    'status_type'     => $h->status_type,
                    'old_status'      => $h->old_status,
                    'new_status'      => $h->new_status,
                    'comment'         => $h->comment,
                    'operator'        => $h->operator,
                    'notify_customer' => (bool)$h->notify_customer,
                    'created_at'      => $h->created_at?->toISOString(),
                ]);
            }),

            // 物流信息
            'shipments' => $this->whenLoaded('shipments', function () {
                return $this->shipments->map(fn($s) => [
                    'id'             => $s->id,
                    'tracking_no'    => $s->tracking_no ?? null,
                    'carrier'        => $s->carrier ?? null,
                    'status'         => $s->status ?? null,
                    'shipped_at'     => isset($s->shipped_at) ? $s->shipped_at?->toISOString() : null,
                    'created_at'     => $s->created_at?->toISOString(),
                ]);
            }),

            // 退款记录
            'refunds' => $this->whenLoaded('refunds', function () {
                return $this->refunds->map(fn($r) => [
                    'id'            => $r->id,
                    'amount'        => isset($r->amount) ? (string)$r->amount : null,
                    'status'        => $r->status ?? null,
                    'reason'        => $r->reason ?? null,
                    'created_at'    => $r->created_at?->toISOString(),
                ]);
            }),

            // 客户信息关联
            'customer' => $this->whenLoaded('customer', fn() => $this->customer ? [
                'id'    => $this->customer->id,
                'name'  => $this->customer->name ?? null,
                'email' => $this->customer->email ?? null,
            ] : null),
        ];
    }

    /**
     * 格式化地址
     */
    private function formatAddress($addr): array
    {
        return [
            'id'           => $addr->id,
            'type'         => $addr->type,
            'firstname'    => $addr->firstname,
            'lastname'     => $addr->lastname,
            'full_name'    => $addr->full_name,
            'company'      => $addr->company,
            'address_1'    => $addr->address_1,
            'address_2'    => $addr->address_2,
            'city'         => $addr->city,
            'postcode'     => $addr->postcode,
            'country'      => $addr->country,
            'country_code' => $addr->country_code,
            'zone'         => $addr->zone,
            'zone_code'    => $addr->zone_code,
            'phone'        => $addr->phone,
            'email'        => $addr->email,
        ];
    }
}
