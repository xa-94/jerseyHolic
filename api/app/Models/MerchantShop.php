<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * 商户店铺（旧模型）— Central DB
 *
 * @deprecated 新架构请使用 App\Models\Central\Store（stancl/tenancy Tenant Model）。
 *             本模型保留用于旧代码中对 jh_merchant_shops 表的引用。
 *
 * 对应表：jh_merchant_shops（central 库）
 */
class MerchantShop extends Model
{
    use SoftDeletes;

    protected $connection = 'central';

    protected $table = 'jh_merchant_shops';

    protected $fillable = [
        'merchant_id', 'website', 'shop_name', 'group_id', 'cc_group_id',
        'token', 'status', 'payment_config',
    ];

    protected $casts = [
        'merchant_id'    => 'integer',
        'group_id'       => 'integer',
        'cc_group_id'    => 'integer',
        'status'         => 'integer',
        'payment_config' => 'array',
    ];

    protected $hidden = ['token'];

    public function merchant(): BelongsTo
    {
        return $this->belongsTo(Merchant::class);
    }

    public function scopeActive($query)
    {
        return $query->where('status', 1);
    }
}
