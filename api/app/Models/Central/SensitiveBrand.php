<?php

declare(strict_types=1);

namespace App\Models\Central;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * 敏感品牌模型 — Central DB（M4-003）
 *
 * 对应表：sensitive_brands（central 库，无 jh_ 前缀）
 * 存储品牌黑名单数据，用于三级判定引擎的 Level 2 — 品牌匹配。
 *
 * @property int              $id
 * @property string           $brand_name       品牌名称
 * @property array|null       $brand_aliases    品牌别名列表
 * @property int|null         $category_l1_id   关联一级品类 ID
 * @property string           $risk_level       风险等级 high/medium/low
 * @property string|null      $reason           标记原因
 * @property int              $status           1=active, 0=inactive
 * @property \Carbon\Carbon   $created_at
 * @property \Carbon\Carbon   $updated_at
 *
 * @property-read ProductCategoryL1|null $categoryL1
 */
class SensitiveBrand extends CentralModel
{
    /** @var string */
    protected $table = 'sensitive_brands';

    /** 状态常量 */
    public const STATUS_ACTIVE   = 1;
    public const STATUS_INACTIVE = 0;

    /** 风险等级 */
    public const RISK_HIGH   = 'high';
    public const RISK_MEDIUM = 'medium';
    public const RISK_LOW    = 'low';

    public const RISK_LEVELS = [self::RISK_HIGH, self::RISK_MEDIUM, self::RISK_LOW];

    /** @var list<string> */
    protected $fillable = [
        'brand_name',
        'brand_aliases',
        'category_l1_id',
        'risk_level',
        'reason',
        'status',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'brand_aliases'  => 'json',
        'category_l1_id' => 'integer',
        'status'         => 'integer',
    ];

    /* ----------------------------------------------------------------
     |  关系
     | ---------------------------------------------------------------- */

    /**
     * 关联一级品类（可为空，null 表示适用所有品类）
     */
    public function categoryL1(): BelongsTo
    {
        return $this->belongsTo(ProductCategoryL1::class, 'category_l1_id');
    }

    /* ----------------------------------------------------------------
     |  Scopes
     | ---------------------------------------------------------------- */

    /**
     * 仅启用的品牌
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_ACTIVE);
    }

    /**
     * 按一级品类筛选（包含 null = 全品类）
     */
    public function scopeOfCategory(Builder $query, ?int $l1Id): Builder
    {
        return $query->where(function (Builder $q) use ($l1Id) {
            $q->whereNull('category_l1_id');
            if ($l1Id !== null) {
                $q->orWhere('category_l1_id', $l1Id);
            }
        });
    }

    /* ----------------------------------------------------------------
     |  业务方法
     | ---------------------------------------------------------------- */

    /**
     * 检查传入的品牌名是否与本条记录匹配（含别名模糊匹配）
     *
     * 匹配逻辑：
     *  1. 精确匹配 brand_name（不区分大小写）
     *  2. 遍历 brand_aliases，不区分大小写比较
     *  3. 模糊匹配：传入品牌名包含 brand_name 或 alias
     */
    public function matchesBrand(string $productBrand): bool
    {
        $normalizedInput = Str::lower(trim($productBrand));
        $normalizedName  = Str::lower(trim($this->brand_name));

        // 精确匹配品牌名
        if ($normalizedInput === $normalizedName) {
            return true;
        }

        // 模糊匹配：输入包含品牌名
        if (Str::contains($normalizedInput, $normalizedName)) {
            return true;
        }

        // 别名匹配
        $aliases = $this->brand_aliases ?? [];
        foreach ($aliases as $alias) {
            $normalizedAlias = Str::lower(trim((string) $alias));
            if ($normalizedInput === $normalizedAlias || Str::contains($normalizedInput, $normalizedAlias)) {
                return true;
            }
        }

        return false;
    }
}
