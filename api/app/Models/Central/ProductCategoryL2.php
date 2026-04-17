<?php

declare(strict_types=1);

namespace App\Models\Central;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * 二级品类模型 — Central DB
 *
 * 对应表：jh_product_categories_l2（central 库，prefix 已配置）
 * 隶属于 L1 一级品类，存储细分品类，name 字段为 JSON 格式支持 16 种语言。
 *
 * @property int              $id
 * @property int              $l1_id          所属 L1 品类 ID
 * @property string           $code           品类编码
 * @property array            $name           多语言名称 JSON
 * @property bool             $is_sensitive   是否敏感品类
 * @property int              $sort_order     排序权重
 * @property int              $status         1=active, 0=inactive
 * @property \Carbon\Carbon   $created_at
 * @property \Carbon\Carbon   $updated_at
 *
 * @property-read ProductCategoryL1 $parent
 */
class ProductCategoryL2 extends CentralModel
{
    /** @var string */
    protected $table = 'product_categories_l2';

    /** 状态常量 */
    public const STATUS_ACTIVE   = 1;
    public const STATUS_INACTIVE = 0;

    /** @var list<string> */
    protected $fillable = [
        'l1_id',
        'code',
        'name',
        'is_sensitive',
        'sort_order',
        'status',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'l1_id'        => 'integer',
        'name'         => 'json',
        'is_sensitive' => 'boolean',
        'sort_order'   => 'integer',
        'status'       => 'integer',
    ];

    /* ----------------------------------------------------------------
     |  关系
     | ---------------------------------------------------------------- */

    /**
     * 所属一级品类
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(ProductCategoryL1::class, 'l1_id');
    }

    /* ----------------------------------------------------------------
     |  Scopes
     | ---------------------------------------------------------------- */

    /**
     * 仅启用的品类
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_ACTIVE);
    }

    /**
     * 仅敏感品类
     */
    public function scopeSensitive(Builder $query): Builder
    {
        return $query->where('is_sensitive', true);
    }

    /**
     * 按 L1 品类筛选
     */
    public function scopeOfParent(Builder $query, int $l1Id): Builder
    {
        return $query->where('l1_id', $l1Id);
    }

    /* ----------------------------------------------------------------
     |  辅助方法
     | ---------------------------------------------------------------- */

    /**
     * 获取指定语言的品类名称
     */
    public function getLocalizedName(string $lang = 'en'): string
    {
        return $this->name[$lang] ?? $this->name['en'] ?? $this->code;
    }
}
