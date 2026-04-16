<?php

namespace App\Models\Central;

use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * 角色模型 — Central DB
 *
 * 对应表：jh_roles（central 库）
 *
 * @property int    $id
 * @property string $name
 * @property string $slug
 * @property string $guard
 * @property string $description
 * @property int    $is_system
 * @property int    $sort_order
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 */
class Role extends CentralModel
{
    protected $table = 'roles';

    protected $fillable = [
        'name',
        'slug',
        'guard',
        'description',
        'is_system',
        'sort_order',
    ];

    protected $casts = [
        'is_system'  => 'boolean',
        'sort_order' => 'integer',
    ];

    /* ----------------------------------------------------------------
     |  关系
     | ---------------------------------------------------------------- */

    public function permissions(): BelongsToMany
    {
        return $this->belongsToMany(Permission::class, 'jh_role_permissions', 'role_id', 'permission_id')
            ->withTimestamps();
    }

    public function admins(): BelongsToMany
    {
        return $this->belongsToMany(Admin::class, 'jh_admin_roles', 'role_id', 'admin_id')
            ->withTimestamps();
    }

    /* ----------------------------------------------------------------
     |  Scopes
     | ---------------------------------------------------------------- */

    public function scopeGuard($query, string $guard)
    {
        return $query->where('guard', $guard);
    }
}
