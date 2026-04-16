<?php

namespace App\Models\Central;

/**
 * 支付账号分组 — Central DB
 *
 * 对应表：jh_payment_account_groups（central 库）
 *
 * @property int    $id
 * @property string $name
 * @property string $type
 * @property string $description
 * @property int    $is_blacklist_group
 * @property int    $status
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 */
class PaymentAccountGroup extends CentralModel
{
    protected $table = 'jh_payment_account_groups';

    protected $fillable = [
        'name', 'type', 'description', 'is_blacklist_group', 'status',
    ];

    protected $casts = [
        'is_blacklist_group' => 'integer',
        'status'             => 'integer',
    ];
}
