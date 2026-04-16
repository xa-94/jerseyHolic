<?php

namespace App\Models\Tenant;

use App\Enums\OrderPaymentStatus;
use App\Enums\OrderShippingStatus;
use App\Enums\OrderRefundStatus;
use App\Enums\OrderDisputeStatus;
use App\Enums\PaymentChannel;
use App\Enums\RiskLevel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * 订单模型 — Tenant DB
 *
 * 对应表：jh_orders
 *
 * @property int    $id
 * @property string $order_no
 * @property string $a_order_no
 * @property string $yy_order_id
 * @property int|null $legacy_oc_order_id
 * @property int|null $legacy_tp_order_id
 * @property int    $customer_id
 * @property int    $merchant_id
 * @property string $a_website
 * @property string $domain
 * @property float  $a_price
 * @property string $currency
 * @property float  $exchange_rate
 * @property float  $price
 * @property float  $shipping_fee
 * @property float  $tax_amount
 * @property float  $discount_amount
 * @property float  $total
 * @property \Carbon\Carbon|null $pay_time
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 * @property \Carbon\Carbon|null $deleted_at
 */
class Order extends TenantModel
{
    use SoftDeletes;

    protected $table = 'jh_orders';

    protected $fillable = [
        'order_no', 'a_order_no', 'yy_order_id',
        'legacy_oc_order_id', 'legacy_tp_order_id',
        'customer_id', 'merchant_id', 'a_website', 'domain',
        'a_price', 'currency', 'exchange_rate', 'price',
        'shipping_fee', 'tax_amount', 'discount_amount', 'total',
        'pay_status', 'shipment_status', 'refund_status', 'dispute_status',
        'pay_type', 'pay_time',
        'paypal_account', 'paypal_email', 'paypal_order_id', 'paypal_transaction_no',
        'stripe_client', 'stripe_session_id',
        'customer_email', 'customer_name', 'customer_phone',
        'is_blacklist', 'is_zw', 'is_diy', 'is_wpz', 'risk_type',
        'deduction_status', 'settlement_status',
        'coupon_code', 'ip', 'user_agent', 'remark',
    ];

    protected $casts = [
        'customer_id'        => 'integer',
        'merchant_id'        => 'integer',
        'legacy_oc_order_id' => 'integer',
        'legacy_tp_order_id' => 'integer',
        'a_price'            => 'decimal:2',
        'exchange_rate'      => 'decimal:8',
        'price'              => 'decimal:2',
        'shipping_fee'       => 'decimal:2',
        'tax_amount'         => 'decimal:2',
        'discount_amount'    => 'decimal:2',
        'total'              => 'decimal:2',
        'pay_status'         => OrderPaymentStatus::class,
        'shipment_status'    => OrderShippingStatus::class,
        'refund_status'      => OrderRefundStatus::class,
        'dispute_status'     => OrderDisputeStatus::class,
        'pay_type'           => PaymentChannel::class,
        'pay_time'           => 'datetime',
        'is_blacklist'       => 'boolean',
        'is_zw'              => 'boolean',
        'is_diy'             => 'boolean',
        'is_wpz'             => 'boolean',
        'risk_type'          => RiskLevel::class,
        'deduction_status'   => 'integer',
        'settlement_status'  => 'integer',
    ];

    /* ----------------------------------------------------------------
     |  关系
     | ---------------------------------------------------------------- */

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'customer_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class, 'order_id');
    }

    public function addresses(): HasMany
    {
        return $this->hasMany(OrderAddress::class, 'order_id');
    }

    public function shippingAddress(): HasOne
    {
        return $this->hasOne(OrderAddress::class, 'order_id')->where('type', 'shipping');
    }

    public function billingAddress(): HasOne
    {
        return $this->hasOne(OrderAddress::class, 'order_id')->where('type', 'billing');
    }

    public function histories(): HasMany
    {
        return $this->hasMany(OrderHistory::class, 'order_id');
    }

    public function totals(): HasMany
    {
        return $this->hasMany(OrderTotal::class, 'order_id');
    }

    public function ext(): HasOne
    {
        return $this->hasOne(OrderExt::class, 'order_id');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class, 'order_id');
    }

    public function refunds(): HasMany
    {
        return $this->hasMany(Refund::class, 'order_id');
    }

    public function disputes(): HasMany
    {
        return $this->hasMany(Dispute::class, 'order_id');
    }

    public function shipments(): HasMany
    {
        return $this->hasMany(Shipment::class, 'order_id');
    }

    public function riskOrder(): HasOne
    {
        return $this->hasOne(RiskOrder::class, 'order_id');
    }

    /* ----------------------------------------------------------------
     |  Scopes
     | ---------------------------------------------------------------- */

    public function scopePaid($query)
    {
        return $query->where('pay_status', OrderPaymentStatus::PAID);
    }

    public function scopePending($query)
    {
        return $query->where('pay_status', OrderPaymentStatus::PENDING);
    }

    public function scopeByDomain($query, string $domain)
    {
        return $query->where('domain', $domain);
    }

    public function scopeBlacklisted($query)
    {
        return $query->where('is_blacklist', 1);
    }

    /* ----------------------------------------------------------------
     |  辅助方法
     | ---------------------------------------------------------------- */

    public function isPaid(): bool
    {
        return $this->pay_status === OrderPaymentStatus::PAID;
    }

    public function isCounterfeit(): bool
    {
        return (bool) $this->is_zw;
    }
}
