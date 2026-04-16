<?php

namespace App\Models\Central;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * 商户风险评分模型 — Central DB
 *
 * 对应表：jh_merchant_risk_scores（central 库）
 * 每个商户一条记录，记录综合风险评分。
 *
 * @property int    $id
 * @property int    $merchant_id
 * @property int    $score
 * @property string $level
 * @property array|null  $factors
 * @property \Carbon\Carbon|null $evaluated_at
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 */
class MerchantRiskScore extends CentralModel
{
    protected $table = 'jh_merchant_risk_scores';

    protected $fillable = [
        'merchant_id',
        'score',
        'level',
        'factors',
        'evaluated_at',
    ];

    protected $casts = [
        'merchant_id'  => 'integer',
        'score'        => 'integer',
        'factors'      => 'array',
        'evaluated_at' => 'datetime',
    ];

    /* ----------------------------------------------------------------
     |  关系
     | ---------------------------------------------------------------- */

    public function merchant(): BelongsTo
    {
        return $this->belongsTo(Merchant::class, 'merchant_id');
    }
}
