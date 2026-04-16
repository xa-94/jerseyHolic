<?php

namespace App\Models\Tenant;

/**
 * 货币模型 — Tenant DB
 *
 * 对应表：jh_currencies
 *
 * @property int    $id
 * @property string $title
 * @property string $code
 * @property string $symbol_left
 * @property string $symbol_right
 * @property int    $decimal_places
 * @property float  $exchange_rate
 * @property int    $sort_order
 * @property int    $status
 * @property int    $is_default
 * @property \Carbon\Carbon|null $rate_updated_at
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 */
class Currency extends TenantModel
{
    protected $table = 'jh_currencies';

    protected $fillable = [
        'title', 'code', 'symbol_left', 'symbol_right', 'decimal_places',
        'exchange_rate', 'sort_order', 'status', 'is_default', 'rate_updated_at',
    ];

    protected $casts = [
        'decimal_places'  => 'integer',
        'exchange_rate'   => 'decimal:8',
        'sort_order'      => 'integer',
        'status'          => 'integer',
        'is_default'      => 'boolean',
        'rate_updated_at' => 'datetime',
    ];

    public function scopeActive($query)
    {
        return $query->where('status', 1);
    }

    /**
     * Convert amount from this currency to USD.
     */
    public function toUsd(float $amount): float
    {
        return round($amount / (float) $this->exchange_rate, 2);
    }
}
