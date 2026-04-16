<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * 商品分类模型 — Tenant DB
 *
 * 对应表：jh_categories（嵌套集模型）
 *
 * @property int    $id
 * @property int    $parent_id
 * @property string|null $image
 * @property int    $sort_order
 * @property int    $status
 * @property int    $_lft
 * @property int    $_rgt
 * @property int    $depth
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 * @property \Carbon\Carbon|null $deleted_at
 */
class Category extends TenantModel
{
    use SoftDeletes;

    protected $table = 'jh_categories';

    protected $fillable = [
        'parent_id', 'image', 'sort_order', 'status',
        '_lft', '_rgt', 'depth',
    ];

    protected $casts = [
        'parent_id'  => 'integer',
        'sort_order' => 'integer',
        'status'     => 'integer',
        '_lft'       => 'integer',
        '_rgt'       => 'integer',
        'depth'      => 'integer',
    ];

    /* ----------------------------------------------------------------
     |  关系
     | ---------------------------------------------------------------- */

    public function descriptions(): HasMany
    {
        return $this->hasMany(CategoryDescription::class, 'category_id');
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    public function products(): BelongsToMany
    {
        return $this->belongsToMany(Product::class, 'jh_product_categories', 'category_id', 'product_id')
            ->withPivot('is_primary')
            ->withTimestamps();
    }

    /* ----------------------------------------------------------------
     |  Scopes
     | ---------------------------------------------------------------- */

    public function scopeActive($query)
    {
        return $query->where('status', 1);
    }

    public function scopeTopLevel($query)
    {
        return $query->where('parent_id', 0);
    }
}
