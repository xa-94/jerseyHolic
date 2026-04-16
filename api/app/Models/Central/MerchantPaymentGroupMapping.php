<?php

namespace App\Models\Central;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * 商户-支付分组映射模型 — Central DB
 *
 * 对应表：jh_merchant_payment_group_mappings（central 库）
 * 实现「商户 + 支付方式 → 使用哪个支付账号分组」的路由规则。
 *
 * @property int    $id
 * @property int    $merchant_id
 * @property string $pay_method
 * @property int    $payment_group_id
 * @property int    $priority
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 */
class MerchantPaymentGroupMapping extends CentralModel
{
    protected $table = 'merchant_payment_group_mappings';

    /** 支付方式常量 */
    public const PAY_METHOD_PAYPAL      = 'paypal';
    public const PAY_METHOD_CREDIT_CARD = 'credit_card';
    public const PAY_METHOD_STRIPE      = 'stripe';
    public const PAY_METHOD_ANTOM       = 'antom';

    public const PAY_METHODS = [
        self::PAY_METHOD_PAYPAL,
        self::PAY_METHOD_CREDIT_CARD,
        self::PAY_METHOD_STRIPE,
        self::PAY_METHOD_ANTOM,
    ];

    protected $fillable = [
        'merchant_id',
        'pay_method',
        'payment_group_id',
        'priority',
    ];

    protected $casts = [
        'merchant_id'      => 'integer',
        'payment_group_id' => 'integer',
        'priority'         => 'integer',
    ];

    /* ----------------------------------------------------------------
     |  关系
     | ---------------------------------------------------------------- */

    /**
     * 所属商户
     */
    public function merchant(): BelongsTo
    {
        return $this->belongsTo(Merchant::class, 'merchant_id');
    }

    /**
     * 关联的支付账号分组
     */
    public function paymentGroup(): BelongsTo
    {
        return $this->belongsTo(PaymentAccountGroup::class, 'payment_group_id');
    }
}
