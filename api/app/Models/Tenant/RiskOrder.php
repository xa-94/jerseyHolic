<?php

namespace App\Models\Tenant;

use App\Enums\RiskLevel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * 风险订单模型 — Tenant DB
 *
 * 对应表：jh_risk_orders
 *
 * @property int    $id
 * @property int    $order_id
 * @property string $risk_level
 * @property string|null $risk_reason
 * @property array|null $risk_factors
 * @property int    $status
 * @property string|null $reviewer
 * @property \Carbon\Carbon|null $reviewed_at
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 */
class RiskOrder extends TenantModel
{
    protected $table = 'jh_risk_orders';

    protected $fillable = [
        'order_id', 'risk_level', 'risk_reason', 'risk_factors',
        'status', 'reviewer', 'reviewed_at',
    ];

    protected $casts = [
        'order_id'     => 'integer',
        'risk_level'   => RiskLevel::class,
        'risk_factors' => 'array',
        'status'       => 'integer',
        'reviewed_at'  => 'datetime',
    ];

    /* ----------------------------------------------------------------
     |  关系
     | ---------------------------------------------------------------- */

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class, 'order_id');
    }

    /* ----------------------------------------------------------------
     |  Scopes
     | ---------------------------------------------------------------- */

    public function scopePending($query)
    {
        return $query->where('status', 1);
    }

    public function scopeHighRisk($query)
    {
        return $query->where('risk_level', RiskLevel::HIGH);
    }
}
