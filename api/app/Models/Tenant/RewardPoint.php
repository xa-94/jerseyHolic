<?php

namespace App\Models\Tenant;

/**
 * 积分记录 — Tenant DB
 *
 * 对应表：jh_reward_points
 */
class RewardPoint extends TenantModel
{
    protected $table = 'jh_reward_points';

    protected $fillable = [
        'customer_id', 'order_id', 'points', 'description', 'type',
    ];

    protected $casts = [
        'customer_id' => 'integer',
        'order_id'    => 'integer',
        'points'      => 'integer',
    ];
}
