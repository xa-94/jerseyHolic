<?php

namespace App\Models\Central;

use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * 支付账号分组 — Central DB
 *
 * 对应表：jh_payment_account_groups（central 库）
 * 通过 group_type 实现四级分组策略：VIP_EXCLUSIVE / STANDARD_SHARED / LITE_SHARED / BLACKLIST_ISOLATED
 *
 * @property int    $id
 * @property string $name
 * @property string $type
 * @property string $group_type
 * @property string $description
 * @property int    $is_blacklist_group
 * @property int    $status
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 */
class PaymentAccountGroup extends CentralModel
{
    protected $table = 'payment_account_groups';

    /** 支付方式类型 */
    public const TYPE_PAYPAL      = 'paypal';
    public const TYPE_CREDIT_CARD = 'credit_card';
    public const TYPE_STRIPE      = 'stripe';
    public const TYPE_ANTOM       = 'antom';

    /** 分组策略类型 */
    public const GROUP_TYPE_VIP_EXCLUSIVE       = 'VIP_EXCLUSIVE';
    public const GROUP_TYPE_STANDARD_SHARED     = 'STANDARD_SHARED';
    public const GROUP_TYPE_LITE_SHARED         = 'LITE_SHARED';
    public const GROUP_TYPE_BLACKLIST_ISOLATED  = 'BLACKLIST_ISOLATED';

    public const GROUP_TYPES = [
        self::GROUP_TYPE_VIP_EXCLUSIVE,
        self::GROUP_TYPE_STANDARD_SHARED,
        self::GROUP_TYPE_LITE_SHARED,
        self::GROUP_TYPE_BLACKLIST_ISOLATED,
    ];

    /** 状态 */
    public const STATUS_DISABLED = 0;
    public const STATUS_ENABLED  = 1;

    protected $fillable = [
        'name', 'type', 'group_type', 'description', 'is_blacklist_group', 'status',
    ];

    protected $casts = [
        'is_blacklist_group' => 'integer',
        'status'             => 'integer',
    ];

    /* ----------------------------------------------------------------
     |  关系
     | ---------------------------------------------------------------- */

    /**
     * 该分组下的支付账号（通过 category_id 关联）
     */
    public function paymentAccounts(): HasMany
    {
        return $this->hasMany(PaymentAccount::class, 'category_id');
    }

    /**
     * 该分组下的信用卡支付账号（通过 cc_category_id 关联）
     */
    public function ccPaymentAccounts(): HasMany
    {
        return $this->hasMany(PaymentAccount::class, 'cc_category_id');
    }

    /**
     * 商户支付分组映射（哪些商户使用了此分组）
     */
    public function merchantMappings(): HasMany
    {
        return $this->hasMany(MerchantPaymentGroupMapping::class, 'payment_group_id');
    }
}
