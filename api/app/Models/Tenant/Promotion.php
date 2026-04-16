<?php

namespace App\Models\Tenant;

/**
 * 促销活动 — Tenant DB
 *
 * 对应表：jh_promotions
 */
class Promotion extends TenantModel
{
    protected $table = 'jh_promotions';

    protected $fillable = [
        'name', 'type', 'discount_value', 'conditions',
        'start_at', 'end_at', 'priority', 'status',
    ];

    protected $casts = [
        'discount_value' => 'decimal:2',
        'conditions'     => 'array',
        'start_at'       => 'datetime',
        'end_at'         => 'datetime',
        'priority'       => 'integer',
        'status'         => 'integer',
    ];

    public function scopeActive($query)
    {
        return $query->where('status', 1);
    }

    public function scopeRunning($query)
    {
        return $query->active()
            ->where(function ($q) {
                $q->whereNull('start_at')->orWhere('start_at', '<=', now());
            })
            ->where(function ($q) {
                $q->whereNull('end_at')->orWhere('end_at', '>=', now());
            });
    }
}
