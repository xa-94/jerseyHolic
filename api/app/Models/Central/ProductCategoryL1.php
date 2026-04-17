<?php

declare(strict_types=1);

namespace App\Models\Central;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * 一级品类模型 — Central DB
 *
 * 对应表：jh_product_categories_l1（central 库，prefix 已配置）
 * 存储运动品类顶层分类，name 字段为 JSON 格式支持 16 种语言。
 *
 * @property int              $id
 * @property string           $code           品类编码
 * @property array            $name           多语言名称 JSON
 * @property string|null      $icon           品类图标 URL
 * @property bool             $is_sensitive   是否敏感品类
 * @property string           $sensitive_ratio 敏感占比百分比
 * @property int              $sort_order     排序权重
 * @property int              $status         1=active, 0=inactive
 * @property \Carbon\Carbon   $created_at
 * @property \Carbon\Carbon   $updated_at
 *
 * @property-read \Illuminate\Database\Eloquent\Collection<ProductCategoryL2> $children
 */
class ProductCategoryL1 extends CentralModel
{
    /** @var string */
    protected $table = 'product_categories_l1';

    /** 状态常量 */
    public const STATUS_ACTIVE   = 1;
    public const STATUS_INACTIVE = 0;

    /** @var list<string> */
    protected $fillable = [
        'code',
        'name',
        'icon',
        'is_sensitive',
        'sensitive_ratio',
        'sort_order',
        'status',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'name'            => 'json',
        'is_sensitive'    => 'boolean',
        'sensitive_ratio' => 'decimal:2',
        'sort_order'      => 'integer',
        'status'          => 'integer',
    ];

    /* ----------------------------------------------------------------
     |  关系
     | ---------------------------------------------------------------- */

    /**
     * 下属二级品类
     */
    public function children(): HasMany
    {
        return $this->hasMany(ProductCategoryL2::class, 'l1_id');
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

    /* ----------------------------------------------------------------
     |  辅助方法
     | ---------------------------------------------------------------- */

    /**
     * 获取指定语言的品类名称
     *
     * 优先返回指定语言名称，不存在则回退至英文，再不存在返回 code。
     */
    public function getLocalizedName(string $lang = 'en'): string
    {
        return $this->name[$lang] ?? $this->name['en'] ?? $this->code;
    }
}
