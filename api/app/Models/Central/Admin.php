<?php

namespace App\Models\Central;

use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Laravel\Sanctum\HasApiTokens;

/**
 * 平台管理员模型 — Central DB
 *
 * 对应表：jh_admins（central 库）
 * 平台超管/运营人员，使用 admin guard 认证。
 *
 * @property int    $id
 * @property string $username
 * @property string $email
 * @property string $password
 * @property string $name
 * @property string|null $avatar
 * @property string|null $phone
 * @property int    $status
 * @property int    $is_super
 * @property int    $login_failures
 * @property \Carbon\Carbon|null $locked_until
 * @property \Carbon\Carbon|null $last_login_at
 * @property string|null $last_login_ip
 * @property string|null $remember_token
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 * @property \Carbon\Carbon|null $deleted_at
 */
class Admin extends Authenticatable
{
    use HasApiTokens, SoftDeletes;

    protected $connection = 'central';

    protected $table = 'jh_admins';

    protected $fillable = [
        'username',
        'email',
        'password',
        'name',
        'avatar',
        'phone',
        'status',
        'is_super',
        'login_failures',
        'locked_until',
        'last_login_at',
        'last_login_ip',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'status'         => 'integer',
        'is_super'       => 'integer',
        'login_failures' => 'integer',
        'locked_until'   => 'datetime',
        'last_login_at'  => 'datetime',
    ];

    /* ----------------------------------------------------------------
     |  关系
     | ---------------------------------------------------------------- */

    /**
     * 管理员拥有的角色（多对多）
     */
    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'jh_admin_roles', 'admin_id', 'role_id')
            ->withTimestamps();
    }

    /* ----------------------------------------------------------------
     |  辅助方法
     | ---------------------------------------------------------------- */

    public function isActive(): bool
    {
        return $this->status === 1;
    }

    public function isSuperAdmin(): bool
    {
        return $this->is_super === 1;
    }

    public function isLocked(): bool
    {
        return $this->locked_until !== null && $this->locked_until->isFuture();
    }

    /**
     * 检查管理员是否拥有指定权限
     */
    public function hasPermission(string $slug): bool
    {
        if ($this->isSuperAdmin()) {
            return true;
        }

        return $this->roles()
            ->whereHas('permissions', fn ($q) => $q->where('slug', $slug))
            ->exists();
    }
}
