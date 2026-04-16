<?php

namespace App\Models\Central;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * PayPal 安全描述映射模型 — Central DB
 *
 * 对应表：jh_paypal_safe_descriptions（central 库）
 * 将商品分类映射为 PayPal 可接受的安全名称和描述。
 *
 * @property int         $id
 * @property int|null    $store_id
 * @property string      $product_category
 * @property string      $safe_name
 * @property string      $safe_description
 * @property string      $safe_category_code
 * @property int         $weight
 * @property int         $status
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 */
class PaypalSafeDescription extends CentralModel
{
    protected $table = 'paypal_safe_descriptions';

    /** 状态常量 */
    public const STATUS_DISABLED = 0;
    public const STATUS_ENABLED  = 1;

    protected $fillable = [
        'store_id',
        'product_category',
        'safe_name',
        'safe_description',
        'safe_category_code',
        'weight',
        'status',
    ];

    protected $casts = [
        'store_id' => 'integer',
        'weight'   => 'integer',
        'status'   => 'integer',
    ];

    /* ----------------------------------------------------------------
     |  关系
     | ---------------------------------------------------------------- */

    /**
     * 关联的站点（nullable，null=全局规则）
     */
    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class, 'store_id');
    }

    /* ----------------------------------------------------------------
     |  Scopes
     | ---------------------------------------------------------------- */

    /**
     * 仅启用的描述
     */
    public function scopeEnabled(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_ENABLED);
    }

    /**
     * 按站点和分类查询（全局规则优先级最低）
     */
    public function scopeForStoreCategory(Builder $query, ?int $storeId, string $category): Builder
    {
        return $query->where('product_category', $category)
            ->where(function (Builder $q) use ($storeId) {
                $q->whereNull('store_id');
                if ($storeId !== null) {
                    $q->orWhere('store_id', $storeId);
                }
            })
            ->orderByDesc('weight');
    }
}
