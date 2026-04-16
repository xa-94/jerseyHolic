<?php

namespace App\Services;

use App\Enums\OrderPaymentStatus;
use App\Enums\OrderRefundStatus;
use App\Enums\OrderShippingStatus;
use App\Enums\ErrorCode;
use App\Enums\SkuCategory;
use App\Exceptions\BusinessException;
use App\Models\Order;
use App\Models\OrderAddress;
use App\Models\OrderHistory;
use App\Models\OrderItem;
use App\Models\OrderTotal;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class OrderService
{
    public function __construct(
        private readonly ProductMappingService $mappingService
    ) {}

    // =========================================================
    // Admin Methods
    // =========================================================

    /**
     * 后台订单分页列表（多条件搜索）
     */
    public function getAdminList(array $params): LengthAwarePaginator
    {
        $query = Order::query()
            ->with(['items', 'shippingAddress', 'customer'])
            ->orderBy('created_at', 'desc');

        // 关键词：订单号 / 客户邮箱 / 客户名
        if (!empty($params['keyword'])) {
            $kw = $params['keyword'];
            $query->where(function ($q) use ($kw) {
                $q->where('order_no', 'like', "%{$kw}%")
                  ->orWhere('customer_email', 'like', "%{$kw}%")
                  ->orWhere('customer_name', 'like', "%{$kw}%");
            });
        }

        // 支付状态
        if (isset($params['pay_status']) && $params['pay_status'] !== '') {
            $query->where('pay_status', (int)$params['pay_status']);
        }

        // 发货状态
        if (isset($params['shipment_status']) && $params['shipment_status'] !== '') {
            $query->where('shipment_status', (int)$params['shipment_status']);
        }

        // 退款状态
        if (isset($params['refund_status']) && $params['refund_status'] !== '') {
            $query->where('refund_status', (int)$params['refund_status']);
        }

        // 日期范围
        if (!empty($params['date_from'])) {
            $query->whereDate('created_at', '>=', $params['date_from']);
        }
        if (!empty($params['date_to'])) {
            $query->whereDate('created_at', '<=', $params['date_to']);
        }

        // 来源域名
        if (!empty($params['domain'])) {
            $query->where('domain', $params['domain']);
        }

        // 支付类型
        if (!empty($params['pay_type'])) {
            $query->where('pay_type', $params['pay_type']);
        }

        // SKU 类型过滤
        if (!empty($params['sku_type'])) {
            $skuType = $params['sku_type'];
            if (in_array($skuType, ['is_zw', 'is_diy', 'is_wpz'])) {
                $query->where($skuType, 1);
            }
        }

        $perPage = min((int)($params['per_page'] ?? 20), 100);
        return $query->paginate($perPage);
    }

    /**
     * 订单详情（eager load 所有关联）
     */
    public function getById(int $id): Order
    {
        return Order::with([
            'items',
            'addresses',
            'histories',
            'totals',
            'ext',
            'payments',
            'refunds',
            'shipments',
            'customer',
        ])->findOrFail($id);
    }

    /**
     * 创建订单（事务）
     */
    public function createOrder(array $data): Order
    {
        return DB::transaction(function () use ($data) {
            // 生成唯一订单号
            $orderNo = $this->generateOrderNo();

            // 自动识别 SKU 类型标记
            $isZw  = false;
            $isDiy = false;
            $isWpz = false;

            // 收集订单商品数据，同时识别 SKU 类型
            $itemsData = $data['items'] ?? [];
            foreach ($itemsData as $item) {
                $skuStr  = $item['sku'] ?? '';
                $category = SkuCategory::fromSku($skuStr);
                if ($category === SkuCategory::IMITATION) {
                    $isZw = true;
                } elseif ($category === SkuCategory::CUSTOM) {
                    $isDiy = true;
                } elseif ($category === SkuCategory::FOREIGN_TRADE) {
                    $isWpz = true;
                }
            }

            // 计算金额汇总
            $subTotal    = array_sum(array_column($itemsData, 'total'));
            $shippingFee = (float)($data['shipping_fee'] ?? 0);
            $taxAmount   = (float)($data['tax_amount'] ?? 0);
            $discount    = (float)($data['discount_amount'] ?? 0);
            $total       = $subTotal + $shippingFee + $taxAmount - $discount;

            // 创建订单主记录
            $order = Order::create([
                'order_no'        => $orderNo,
                'customer_id'     => $data['customer_id'] ?? null,
                'merchant_id'     => $data['merchant_id'] ?? null,
                'domain'          => $data['domain'] ?? null,
                'currency'        => $data['currency'] ?? 'USD',
                'exchange_rate'   => $data['exchange_rate'] ?? 1,
                'price'           => $subTotal,
                'shipping_fee'    => $shippingFee,
                'tax_amount'      => $taxAmount,
                'discount_amount' => $discount,
                'total'           => $total,
                'pay_status'      => OrderPaymentStatus::PENDING,
                'shipment_status' => OrderShippingStatus::UNPROCESSED,
                'refund_status'   => OrderRefundStatus::NONE,
                'pay_type'        => $data['pay_type'] ?? null,
                'customer_email'  => $data['customer_email'] ?? null,
                'customer_name'   => $data['customer_name'] ?? null,
                'customer_phone'  => $data['customer_phone'] ?? null,
                'is_zw'           => $isZw,
                'is_diy'          => $isDiy,
                'is_wpz'          => $isWpz,
                'coupon_code'     => $data['coupon_code'] ?? null,
                'ip'              => $data['ip'] ?? null,
                'user_agent'      => $data['user_agent'] ?? null,
                'remark'          => $data['remark'] ?? null,
            ]);

            // 创建订单商品项（含 safe_name）
            foreach ($itemsData as $item) {
                $safeName = $item['safe_name'] ?? $item['name'];
                OrderItem::create([
                    'order_id'       => $order->id,
                    'product_id'     => $item['product_id'] ?? null,
                    'product_sku_id' => $item['product_sku_id'] ?? null,
                    'sku'            => $item['sku'] ?? '',
                    'name'           => $item['name'],
                    'safe_name'      => $safeName,
                    'image'          => $item['image'] ?? null,
                    'quantity'       => (int)$item['quantity'],
                    'price'          => (float)$item['price'],
                    'total'          => (float)$item['total'],
                    'weight'         => (float)($item['weight'] ?? 0),
                    'options'        => $item['options'] ?? null,
                ]);
            }

            // 创建订单地址（shipping + billing）
            $addresses = $data['addresses'] ?? [];
            foreach ($addresses as $address) {
                OrderAddress::create(array_merge(
                    ['order_id' => $order->id],
                    array_intersect_key($address, array_flip([
                        'type', 'firstname', 'lastname', 'company',
                        'address_1', 'address_2', 'city', 'postcode',
                        'country', 'country_code', 'zone', 'zone_code',
                        'phone', 'email',
                    ]))
                ));
            }

            // 创建订单金额明细（OrderTotal）
            $totalsConfig = [
                ['code' => 'sub_total', 'title' => 'Sub-Total',  'value' => $subTotal,    'sort_order' => 1],
                ['code' => 'shipping',  'title' => 'Shipping',   'value' => $shippingFee, 'sort_order' => 2],
                ['code' => 'tax',       'title' => 'Tax',        'value' => $taxAmount,   'sort_order' => 3],
                ['code' => 'discount',  'title' => 'Discount',   'value' => -$discount,   'sort_order' => 4],
                ['code' => 'total',     'title' => 'Total',      'value' => $total,       'sort_order' => 5],
            ];
            foreach ($totalsConfig as $totalRow) {
                OrderTotal::create(array_merge(['order_id' => $order->id], $totalRow));
            }

            // 记录创建历史
            $this->addHistory($order->id, '订单已创建', $data['operator_id'] ?? null);

            return $order->load(['items', 'addresses', 'totals']);
        });
    }

    /**
     * 更新支付状态，记录历史
     */
    public function updatePaymentStatus(
        int $id,
        OrderPaymentStatus $status,
        ?string $remark = null,
        ?int $operatorId = null
    ): Order {
        return DB::transaction(function () use ($id, $status, $remark, $operatorId) {
            $order = Order::findOrFail($id);
            $oldStatus = $order->pay_status->value;

            $order->update(['pay_status' => $status]);

            $comment = "支付状态变更：{$order->pay_status->label()} → {$status->label()}";
            if ($remark) {
                $comment .= "。备注：{$remark}";
            }

            $this->addHistory($id, $comment, $operatorId, false, 'pay_status', $oldStatus, $status->value);

            return $order->fresh();
        });
    }

    /**
     * 更新发货状态，记录历史
     */
    public function updateShippingStatus(
        int $id,
        OrderShippingStatus $status,
        ?string $remark = null,
        ?int $operatorId = null
    ): Order {
        return DB::transaction(function () use ($id, $status, $remark, $operatorId) {
            $order = Order::findOrFail($id);
            $oldStatus = $order->shipment_status->value;

            $order->update(['shipment_status' => $status]);

            $comment = "发货状态变更：{$order->shipment_status->label()} → {$status->label()}";
            if ($remark) {
                $comment .= "。备注：{$remark}";
            }

            $this->addHistory($id, $comment, $operatorId, false, 'shipment_status', $oldStatus, $status->value);

            return $order->fresh();
        });
    }

    /**
     * 退款处理（全额/部分），更新退款状态，记录历史
     */
    public function processRefund(int $id, array $data): Order
    {
        return DB::transaction(function () use ($id, $data) {
            $order = Order::findOrFail($id);

            if (!$order->isPaid()) {
                throw new BusinessException(ErrorCode::BUSINESS_ERROR, '只有已支付订单才能退款');
            }

            $refundAmount = (float)($data['refund_amount'] ?? 0);
            if ($refundAmount <= 0) {
                throw new BusinessException(ErrorCode::PARAM_ERROR, '退款金额必须大于0');
            }
            if ($refundAmount > $order->total) {
                throw new BusinessException(ErrorCode::PARAM_ERROR, '退款金额不能超过订单总额');
            }

            // 判断全额还是部分退款
            $isFullRefund = abs($refundAmount - $order->total) < 0.01;

            $newPayStatus    = $isFullRefund ? OrderPaymentStatus::REFUNDING     : OrderPaymentStatus::PARTIAL_REFUNDING;
            $newRefundStatus = $isFullRefund ? OrderRefundStatus::REFUNDING       : OrderRefundStatus::PARTIAL_REFUNDING;

            $order->update([
                'pay_status'    => $newPayStatus,
                'refund_status' => $newRefundStatus,
            ]);

            $comment = sprintf(
                '申请退款 $%.2f（%s）',
                $refundAmount,
                $isFullRefund ? '全额退款' : '部分退款'
            );
            if (!empty($data['reason'])) {
                $comment .= "，原因：{$data['reason']}";
            }

            $this->addHistory($id, $comment, $data['operator_id'] ?? null);

            Log::info('订单退款申请', [
                'order_id'      => $id,
                'order_no'      => $order->order_no,
                'refund_amount' => $refundAmount,
                'operator_id'   => $data['operator_id'] ?? null,
            ]);

            return $order->fresh();
        });
    }

    /**
     * 取消订单（仅待支付状态可取消）
     */
    public function cancelOrder(int $id, ?string $reason = null): Order
    {
        return DB::transaction(function () use ($id, $reason) {
            $order = Order::findOrFail($id);

            if ($order->pay_status !== OrderPaymentStatus::PENDING) {
                throw new BusinessException(ErrorCode::BUSINESS_ERROR, '只有待支付订单才能取消');
            }

            $order->update(['pay_status' => OrderPaymentStatus::CANCELLED]);

            $comment = '订单已取消';
            if ($reason) {
                $comment .= "，原因：{$reason}";
            }

            $this->addHistory($id, $comment);

            return $order->fresh();
        });
    }

    /**
     * 添加订单历史记录
     */
    public function addHistory(
        int $orderId,
        string $comment,
        ?int $operatorId = null,
        bool $notifyCustomer = false,
        ?string $statusType = null,
        ?int $oldStatus = null,
        ?int $newStatus = null
    ): OrderHistory {
        return OrderHistory::create([
            'order_id'        => $orderId,
            'status_type'     => $statusType,
            'old_status'      => $oldStatus,
            'new_status'      => $newStatus,
            'comment'         => $comment,
            'operator'        => $operatorId,
            'notify_customer' => $notifyCustomer,
        ]);
    }

    // =========================================================
    // Buyer Methods
    // =========================================================

    /**
     * 买家订单列表（数据隔离）
     */
    public function getBuyerOrders(int $customerId, array $params): LengthAwarePaginator
    {
        $query = Order::where('customer_id', $customerId)
            ->with(['items', 'shippingAddress'])
            ->orderBy('created_at', 'desc');

        // 支付状态过滤
        if (isset($params['pay_status']) && $params['pay_status'] !== '') {
            $query->where('pay_status', (int)$params['pay_status']);
        }

        $perPage = min((int)($params['per_page'] ?? 10), 50);
        return $query->paginate($perPage);
    }

    /**
     * 买家订单详情（严格数据隔离）
     */
    public function getBuyerOrderDetail(int $customerId, int $orderId): Order
    {
        return Order::where('customer_id', $customerId)
            ->with([
                'items',
                'addresses',
                'totals',
                'histories' => fn($q) => $q->where('notify_customer', true)->orderBy('created_at'),
                'shipments',
            ])
            ->findOrFail($orderId);
    }

    // =========================================================
    // Private Helpers
    // =========================================================

    /**
     * 生成唯一订单号（格式: JH + 年月日 + 6位序号，如 JH20260416000001）
     */
    private function generateOrderNo(): string
    {
        $prefix = 'JH' . now()->format('Ymd');

        // 使用数据库锁获取当日最大序号
        $lastOrder = Order::where('order_no', 'like', "{$prefix}%")
            ->orderBy('order_no', 'desc')
            ->lockForUpdate()
            ->first();

        if ($lastOrder) {
            $lastSeq = (int)substr($lastOrder->order_no, -6);
            $seq = $lastSeq + 1;
        } else {
            $seq = 1;
        }

        $orderNo = $prefix . str_pad($seq, 6, '0', STR_PAD_LEFT);

        // 防止极端并发重复（二次校验）
        if (Order::where('order_no', $orderNo)->exists()) {
            $orderNo = $prefix . str_pad($seq + 1, 6, '0', STR_PAD_LEFT);
        }

        return $orderNo;
    }
}
