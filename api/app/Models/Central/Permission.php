<?php

namespace App\Models\Central;

use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * 权限模型 — Central DB
 *
 * 对应表：jh_permissions（central 库）
 *
 * @property int    $id
 * @property string $name
 * @property string $slug
 * @property string $module
 * @property string $action
 * @property string $description
 * @property int    $sort_order
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 */
class Permission extends CentralModel
{
    protected $table = 'jh_permissions';

    protected $fillable = [
        'name',
        'slug',
        'module',
        'action',
        'description',
        'sort_order',
    ];

    protected $casts = [
        'sort_order' => 'integer',
    ];

    /* ----------------------------------------------------------------
     |  关系
     | ---------------------------------------------------------------- */

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'jh_role_permissions', 'permission_id', 'role_id')
            ->withTimestamps();
    }

    /* ----------------------------------------------------------------
     |  Scopes
     | ---------------------------------------------------------------- */

    public function scopeModule($query, string $module)
    {
        return $query->where('module', $module);
    }
}
