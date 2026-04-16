<?php

namespace App\Models\Central;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Laravel\Sanctum\HasApiTokens;

/**
 * 商户用户/操作员模型 — Central DB
 *
 * 对应表：jh_merchant_users（central 库）
 * 商户侧操作人员，实现 Authenticatable 接口用于 Sanctum merchant guard 认证。
 *
 * @property int    $id
 * @property int    $merchant_id
 * @property string $username
 * @property string $email
 * @property string $password
 * @property string $name
 * @property string|null $phone
 * @property string|null $avatar
 * @property string $role
 * @property array|null  $allowed_store_ids
 * @property int    $status
 * @property int    $login_failures
 * @property \Carbon\Carbon|null $locked_until
 * @property \Carbon\Carbon|null $last_login_at
 * @property string|null $last_login_ip
 * @property string|null $remember_token
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 * @property \Carbon\Carbon|null $deleted_at
 */
class MerchantUser extends Authenticatable
{
    use HasApiTokens, SoftDeletes;

    /**
     * 强制使用 central 数据库连接。
     */
    protected $connection = 'central';

    protected $table = 'jh_merchant_users';

    protected $fillable = [
        'merchant_id',
        'username',
        'email',
        'password',
        'name',
        'phone',
        'avatar',
        'role',
        'allowed_store_ids',
        'status',
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
        'merchant_id'      => 'integer',
        'allowed_store_ids' => 'array',
        'status'           => 'integer',
        'login_failures'   => 'integer',
        'locked_until'     => 'datetime',
        'last_login_at'    => 'datetime',
    ];

    /* ----------------------------------------------------------------
     |  关系
     | ---------------------------------------------------------------- */

    /**
     * 所属商户
     */
    public function merchant(): BelongsTo
    {
        return $this->belongsTo(Merchant::class, 'merchant_id');
    }

    /* ----------------------------------------------------------------
     |  辅助方法
     | ---------------------------------------------------------------- */

    /**
     * 判断用户是否启用
     */
    public function isActive(): bool
    {
        return $this->status === 1;
    }

    /**
     * 判断用户是否被锁定
     */
    public function isLocked(): bool
    {
        return $this->locked_until !== null && $this->locked_until->isFuture();
    }

    /**
     * 判断用户是否有权访问指定店铺
     */
    public function canAccessStore(int $storeId): bool
    {
        // allowed_store_ids 为 null 表示可访问所有店铺
        if ($this->allowed_store_ids === null) {
            return true;
        }

        return in_array($storeId, $this->allowed_store_ids, true);
    }
}
