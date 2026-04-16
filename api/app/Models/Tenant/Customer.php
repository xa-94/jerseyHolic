<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Laravel\Sanctum\HasApiTokens;

/**
 * 买家/客户模型 — Tenant DB
 *
 * 对应表：jh_customers
 * 实现 Authenticatable 用于买家前台登录认证。
 *
 * @property int    $id
 * @property int    $customer_group_id
 * @property string $firstname
 * @property string $lastname
 * @property string $email
 * @property string $password
 * @property string|null $phone
 * @property int    $newsletter
 * @property int    $status
 * @property string|null $ip
 * @property string $language_code
 * @property string $currency_code
 * @property int    $login_failures
 * @property \Carbon\Carbon|null $locked_until
 * @property \Carbon\Carbon|null $last_login_at
 * @property string|null $remember_token
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 * @property \Carbon\Carbon|null $deleted_at
 */
class Customer extends Authenticatable
{
    use HasApiTokens, SoftDeletes;

    // 不显式设置 $connection，由 stancl/tenancy 自动切换到当前租户数据库。

    protected $table = 'jh_customers';

    protected $fillable = [
        'customer_group_id', 'firstname', 'lastname', 'email', 'password',
        'phone', 'newsletter', 'status', 'ip', 'language_code', 'currency_code',
        'login_failures', 'locked_until', 'last_login_at',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'customer_group_id' => 'integer',
        'newsletter'        => 'boolean',
        'status'            => 'integer',
        'login_failures'    => 'integer',
        'locked_until'      => 'datetime',
        'last_login_at'     => 'datetime',
    ];

    /* ----------------------------------------------------------------
     |  关系
     | ---------------------------------------------------------------- */

    public function group(): BelongsTo
    {
        return $this->belongsTo(CustomerGroup::class, 'customer_group_id');
    }

    public function addresses(): HasMany
    {
        return $this->hasMany(CustomerAddress::class, 'customer_id');
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class, 'customer_id');
    }

    public function rewardPoints(): HasMany
    {
        return $this->hasMany(RewardPoint::class, 'customer_id');
    }

    /* ----------------------------------------------------------------
     |  Scopes
     | ---------------------------------------------------------------- */

    public function scopeActive($query)
    {
        return $query->where('status', 1);
    }

    /* ----------------------------------------------------------------
     |  访问器
     | ---------------------------------------------------------------- */

    public function getFullNameAttribute(): string
    {
        return trim("{$this->firstname} {$this->lastname}");
    }

    public function isLocked(): bool
    {
        return $this->locked_until && $this->locked_until->isFuture();
    }
}
