<?php

namespace App\Models\Central;

use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * 后台菜单模型 — Central DB
 *
 * 对应表：jh_menus（central 库）
 *
 * @property int    $id
 * @property int    $parent_id
 * @property string $title
 * @property string $icon
 * @property string $path
 * @property string $permission_slug
 * @property int    $type
 * @property int    $is_visible
 * @property int    $sort_order
 * @property int    $status
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 */
class Menu extends CentralModel
{
    protected $table = 'menus';

    protected $fillable = [
        'parent_id',
        'title',
        'icon',
        'path',
        'permission_slug',
        'type',
        'is_visible',
        'sort_order',
        'status',
    ];

    protected $casts = [
        'parent_id'  => 'integer',
        'type'       => 'integer',
        'is_visible' => 'boolean',
        'sort_order' => 'integer',
        'status'     => 'integer',
    ];

    /* ----------------------------------------------------------------
     |  关系
     | ---------------------------------------------------------------- */

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    /**
     * 递归加载子菜单树
     */
    public function allChildren(): HasMany
    {
        return $this->children()->with('allChildren');
    }

    /* ----------------------------------------------------------------
     |  Scopes
     | ---------------------------------------------------------------- */

    public function scopeVisible($query)
    {
        return $query->where('is_visible', 1)->where('status', 1);
    }

    public function scopeTopLevel($query)
    {
        return $query->where('parent_id', 0);
    }
}
