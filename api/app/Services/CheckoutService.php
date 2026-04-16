<?php

namespace App\Services;

use App\Enums\OrderPaymentStatus;
use App\Enums\OrderShippingStatus;
use App\Enums\OrderRefundStatus;
use App\Enums\OrderDisputeStatus;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderAddress;
use App\Models\Product;
use App\Models\ProductSku;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class CheckoutService
{
    public function __construct(
        private readonly CartService $cartService
    ) {}

    // =========================================================
    // Public API
    // =========================================================

    /**
     * 预览订单（不创建）
     *
     * @param array $data {
     *   address?: array,   // 收货地址（预留）
     *   coupon_code?: string,
     *   shipping_method?: string,
     * }
     */
    public function preview(?int $customerId, ?string $sessionId, array $data): array
    {
        $cart = $this->cartService->getCart($customerId, $sessionId);

        if (empty($cart['items'])) {
            throw new \RuntimeException('购物车为空', 40010);
        }

        // 二次库存验证
        $this->validateCartStock($cart['items']);

        $subtotal     = (float)$cart['total'];
        $shippingFee  = 0.00;   // TODO: 接入 ShippingRateService
        $tax          = 0.00;   // TODO: 接入税务计算
        $discount     = 0.00;   // TODO: 接入优惠券服务
        $total        = round($subtotal + $shippingFee + $tax - $discount, 2);

        return [
            'items'        => $cart['items'],
            'item_count'   => $cart['item_count'],
            'subtotal'     => $subtotal,
            'shipping_fee' => $shippingFee,
            'tax'          => $tax,
            'discount'     => $discount,
            'total'        => $total,
            'currency'     => 'USD',
        ];
    }

    /**
     * 提交订单
     *
     * @param array $data {
     *   shipping_address: array,  // 必填
     *   billing_address?: array,
     *   coupon_code?: string,
     *   remark?: string,
     *   domain?: string,
     * }
     */
    public function submit(?int $customerId, ?string $sessionId, array $data): Order
    {
        // 再次预览，确保价格与库存一致
        $preview = $this->preview($customerId, $sessionId, $data);

        return DB::transaction(function () use ($customerId, $sessionId, $preview, $data) {
            // 创建订单
            $order = $this->createOrderFromPreview($customerId, $preview, $data);

            // 保存收货地址
            if (!empty($data['shipping_address'])) {
                $this->saveOrderAddress($order->id, $data['shipping_address'], 'shipping');
            }
            if (!empty($data['billing_address'])) {
                $this->saveOrderAddress($order->id, $data['billing_address'], 'billing');
            }

            // 创建订单明细
            $this->createOrderItems($order->id, $preview['items']);

            // 清空购物车
            $this->cartService->clear($customerId, $sessionId);

            Log::info('订单已创建', [
                'order_no'    => $order->order_no,
                'customer_id' => $customerId,
                'total'       => $order->total,
            ]);

            return $order->load(['items', 'shippingAddress']);
        });
    }

    // =========================================================
    // Internal Helpers
    // =========================================================

    /**
     * 验证购物车内所有商品库存
     */
    private function validateCartStock(array $items): void
    {
        foreach ($items as $item) {
            $productId = (int)$item['product_id'];
            $skuId     = $item['sku_id'] ?? null;
            $quantity  = (int)$item['quantity'];

            if ($skuId) {
                $sku = ProductSku::find($skuId);
                if (!$sku || (int)$sku->quantity < $quantity) {
                    $name = $item['name'] . ($item['sku_name'] ? " ({$item['sku_name']})" : '');
                    throw new \RuntimeException("商品「{$name}」库存不足", 40403);
                }
            } else {
                $product = Product::find($productId);
                if (!$product || (int)$product->quantity < $quantity) {
                    throw new \RuntimeException("商品「{$item['name']}」库存不足", 40403);
                }
            }
        }
    }

    /**
     * 根据预览数据创建订单主记录
     *
     * NOTE: OrderService 由另一位开发者同步开发，当其就绪后，应将此处替换为：
     *   app(OrderService::class)->createOrder($customerId, $preview, $data);
     * TODO: 待 OrderService::createOrder() 可用后，统一迁移调用。
     */
    private function createOrderFromPreview(?int $customerId, array $preview, array $data): Order
    {
        $shippingAddr = $data['shipping_address'] ?? [];

        $orderNo = $this->generateOrderNo();

        return Order::create([
            'order_no'        => $orderNo,
            'customer_id'     => $customerId,
            'domain'          => $data['domain'] ?? config('app.url'),
            'price'           => $preview['subtotal'],
            'shipping_fee'    => $preview['shipping_fee'],
            'tax_amount'      => $preview['tax'],
            'discount_amount' => $preview['discount'],
            'total'           => $preview['total'],
            'currency'        => $preview['currency'] ?? 'USD',
            'pay_status'      => OrderPaymentStatus::PENDING,
            'shipment_status' => OrderShippingStatus::PENDING,
            'refund_status'   => OrderRefundStatus::NONE,
            'dispute_status'  => OrderDisputeStatus::NONE,
            'customer_name'   => trim(($shippingAddr['first_name'] ?? $shippingAddr['firstname'] ?? '') . ' ' . ($shippingAddr['last_name'] ?? $shippingAddr['lastname'] ?? '')),
            'customer_email'  => $shippingAddr['email'] ?? null,
            'customer_phone'  => $shippingAddr['phone'] ?? null,
            'coupon_code'     => $data['coupon_code'] ?? null,
            'remark'          => $data['remark'] ?? null,
        ]);
    }

    /**
     * 保存订单地址
     */
    private function saveOrderAddress(int $orderId, array $addr, string $type): void
    {
        OrderAddress::create([
            'order_id'    => $orderId,
            'type'        => $type,
            'firstname'   => $addr['first_name'] ?? $addr['firstname'] ?? '',
            'lastname'    => $addr['last_name'] ?? $addr['lastname'] ?? '',
            'company'     => $addr['company'] ?? null,
            'address_1'   => $addr['address_1'] ?? '',
            'address_2'   => $addr['address_2'] ?? null,
            'city'        => $addr['city'] ?? '',
            'zone'        => $addr['state'] ?? $addr['zone'] ?? null,
            'zone_code'   => $addr['state_code'] ?? $addr['zone_code'] ?? null,
            'postcode'    => $addr['postcode'] ?? '',
            'country'     => $addr['country'] ?? '',
            'country_code'=> $addr['country_code'] ?? null,
            'phone'       => $addr['phone'] ?? null,
            'email'       => $addr['email'] ?? null,
        ]);
    }

    /**
     * 创建订单明细（锁定价格）
     */
    private function createOrderItems(int $orderId, array $items): void
    {
        foreach ($items as $item) {
            OrderItem::create([
                'order_id'       => $orderId,
                'product_id'     => $item['product_id'],
                'product_sku_id' => $item['sku_id'] ?? null,
                'name'           => $item['name'],
                'image'          => $item['image'] ?? null,
                'price'          => $item['price'],
                'quantity'       => $item['quantity'],
                'total'          => $item['subtotal'],
                'options'        => $item['sku_name'] ? [['name' => 'SKU', 'value' => $item['sku_name']]] : null,
            ]);
        }
    }

    /**
     * 生成唯一订单号（格式: JH + yyyyMMdd + 8位随机大写字母数字）
     */
    private function generateOrderNo(): string
    {
        do {
            $no = 'JH' . date('Ymd') . strtoupper(Str::random(8));
        } while (Order::where('order_no', $no)->exists());

        return $no;
    }
}
