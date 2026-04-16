<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * 信用卡临时信息 — Tenant DB
 *
 * 对应表：jh_payment_cards
 */
class PaymentCard extends TenantModel
{
    protected $table = 'jh_payment_cards';

    protected $fillable = [
        'order_id', 'card_name', 'card_number_masked', 'card_brand',
        'expiry', 'token', 'is_3ds',
    ];

    protected $casts = [
        'order_id' => 'integer',
        'is_3ds'   => 'integer',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class, 'order_id');
    }
}
