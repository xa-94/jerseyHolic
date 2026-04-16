<?php

namespace App\Models\Central;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\Pivot;

/**
 * 店铺-支付账号关联（Pivot Model）— Central DB
 *
 * 对应表：store_payment_accounts（central 库）
 * 多对多中间表，关联 Store 与 PaymentAccount。
 *
 * @property int  $id
 * @property int  $store_id
 * @property int  $payment_account_id
 * @property int  $priority
 * @property int  $is_active
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 */
class StorePaymentAccount extends Pivot
{
    /**
     * 强制使用 central 数据库连接。
     */
    protected $connection = 'central';

    protected $table = 'store_payment_accounts';

    public $incrementing = true;

    protected $fillable = [
        'store_id',
        'payment_account_id',
        'priority',
        'is_active',
    ];

    protected $casts = [
        'store_id'            => 'integer',
        'payment_account_id'  => 'integer',
        'priority'            => 'integer',
        'is_active'           => 'integer',
    ];

    /* ----------------------------------------------------------------
     |  关系
     | ---------------------------------------------------------------- */

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class, 'store_id');
    }

    public function paymentAccount(): BelongsTo
    {
        return $this->belongsTo(PaymentAccount::class, 'payment_account_id');
    }
}
