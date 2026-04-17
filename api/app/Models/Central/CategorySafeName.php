<?php

declare(strict_types=1);

namespace App\Models\Central;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * 品类级安全映射名称模型 — Central DB（M4-002）
 *
 * 对应表：jh_category_safe_names（central 库，prefix 已配置）
 * 存储仿牌商品→普货的多语言安全名称映射，
 * 支持按品类、SKU 前缀、站点级覆盖等维度管理。
 *
 * @property int              $id
 * @property int|null         $category_l1_id   关联 L1 品类
 * @property int|null         $category_l2_id   关联 L2 品类
 * @property string|null      $sku_prefix       SKU 前缀匹配
 * @property int|null         $store_id         站点级覆盖（null=全局）
 * @property string           $safe_name_en     英文安全名称
 * @property string|null      $safe_name_zh
 * @property string|null      $safe_name_de
 * @property string|null      $safe_name_fr
 * @property string|null      $safe_name_es
 * @property string|null      $safe_name_it
 * @property string|null      $safe_name_pt
 * @property string|null      $safe_name_nl
 * @property string|null      $safe_name_pl
 * @property string|null      $safe_name_sv
 * @property string|null      $safe_name_da
 * @property string|null      $safe_name_ar
 * @property string|null      $safe_name_tr
 * @property string|null      $safe_name_el
 * @property string|null      $safe_name_ja
 * @property string|null      $safe_name_ko
 * @property int              $weight           加权随机选取权重
 * @property int              $status           1=active, 0=inactive
 * @property \Carbon\Carbon   $created_at
 * @property \Carbon\Carbon   $updated_at
 *
 * @property-read ProductCategoryL1|null $categoryL1
 * @property-read ProductCategoryL2|null $categoryL2
 * @property-read Store|null             $store
 */
class CategorySafeName extends CentralModel
{
    /** @var string */
    protected $table = 'category_safe_names';

    /** 状态常量 */
    public const STATUS_ACTIVE   = 1;
    public const STATUS_INACTIVE = 0;

    /** 支持的语言列表 */
    public const SUPPORTED_LOCALES = [
        'en', 'zh', 'de', 'fr', 'es', 'it', 'pt', 'nl',
        'pl', 'sv', 'da', 'ar', 'tr', 'el', 'ja', 'ko',
    ];

    /** @var list<string> */
    protected $fillable = [
        'category_l1_id',
        'category_l2_id',
        'sku_prefix',
        'store_id',
        'safe_name_en',
        'safe_name_zh',
        'safe_name_de',
        'safe_name_fr',
        'safe_name_es',
        'safe_name_it',
        'safe_name_pt',
        'safe_name_nl',
        'safe_name_pl',
        'safe_name_sv',
        'safe_name_da',
        'safe_name_ar',
        'safe_name_tr',
        'safe_name_el',
        'safe_name_ja',
        'safe_name_ko',
        'weight',
        'status',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'category_l1_id' => 'integer',
        'category_l2_id' => 'integer',
        'store_id'       => 'integer',
        'weight'         => 'integer',
        'status'         => 'integer',
    ];

    /* ----------------------------------------------------------------
     |  关系
     | ---------------------------------------------------------------- */

    /**
     * 关联的 L1 品类
     */
    public function categoryL1(): BelongsTo
    {
        return $this->belongsTo(ProductCategoryL1::class, 'category_l1_id');
    }

    /**
     * 关联的 L2 品类
     */
    public function categoryL2(): BelongsTo
    {
        return $this->belongsTo(ProductCategoryL2::class, 'category_l2_id');
    }

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
     * 仅启用的记录
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_ACTIVE);
    }

    /**
     * 按品类筛选（L1 + L2）
     */
    public function scopeForCategory(Builder $query, ?int $l1Id, ?int $l2Id = null): Builder
    {
        if ($l1Id !== null) {
            $query->where('category_l1_id', $l1Id);
        }
        if ($l2Id !== null) {
            $query->where('category_l2_id', $l2Id);
        }

        return $query;
    }

    /**
     * 按站点筛选
     */
    public function scopeForStore(Builder $query, ?int $storeId): Builder
    {
        if ($storeId !== null) {
            return $query->where('store_id', $storeId);
        }

        return $query->whereNull('store_id');
    }

    /**
     * 按 SKU 前缀筛选
     */
    public function scopeForSkuPrefix(Builder $query, string $prefix): Builder
    {
        return $query->where('sku_prefix', $prefix);
    }

    /* ----------------------------------------------------------------
     |  辅助方法
     | ---------------------------------------------------------------- */

    /**
     * 获取指定语言的安全名称（fallback 到 en）
     */
    public function getSafeName(string $locale = 'en'): string
    {
        $column = "safe_name_{$locale}";

        return $this->{$column} ?? $this->safe_name_en;
    }
}
