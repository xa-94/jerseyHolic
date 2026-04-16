<?php

namespace App\Models\Central;

use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * 商户模型 — Central DB
 *
 * 对应表：jh_merchants（central 库）
 * 一个商户可拥有多个店铺（Store），每个店铺对应一个 Tenant DB。
 *
 * @property int    $id
 * @property string $merchant_name
 * @property string $email
 * @property string $password
 * @property string $contact_name
 * @property string|null $phone
 * @property string|null $merchant_id
 * @property string|null $api_key
 * @property string|null $api_secret
 * @property string $level             商户等级: starter|standard|advanced|vip
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
class Merchant extends CentralModel
{
    use SoftDeletes;

    protected $table = 'jh_merchants';

    protected $fillable = [
        'merchant_name',
        'email',
        'password',
        'contact_name',
        'phone',
        'merchant_id',
        'api_key',
        'api_secret',
        'level',
        'status',
        'login_failures',
        'locked_until',
        'last_login_at',
        'last_login_ip',
    ];

    protected $hidden = [
        'password',
        'api_key',
        'api_secret',
        'remember_token',
    ];

    protected $casts = [
        'level'          => 'string',
        'status'         => 'integer',
        'login_failures' => 'integer',
        'locked_until'   => 'datetime',
        'last_login_at'  => 'datetime',
    ];

    /* ----------------------------------------------------------------
     |  关系
     | ---------------------------------------------------------------- */

    /**
     * 商户拥有的店铺列表
     */
    public function stores(): HasMany
    {
        return $this->hasMany(Store::class, 'merchant_id');
    }

    /**
     * 商户的用户/操作员
     */
    public function merchantUsers(): HasMany
    {
        return $this->hasMany(MerchantUser::class, 'merchant_id');
    }

    /**
     * 商户的 API 密钥
     */
    public function apiKeys(): HasMany
    {
        return $this->hasMany(MerchantApiKey::class, 'merchant_id');
    }

    /**
     * 商户的结算记录
     */
    public function settlementRecords(): HasMany
    {
        return $this->hasMany(SettlementRecord::class, 'merchant_id');
    }

    /**
     * 商户风险评分（一对一）
     */
    public function riskScore(): HasOne
    {
        return $this->hasOne(MerchantRiskScore::class, 'merchant_id');
    }

    /**
     * 商户的商品同步日志
     */
    public function productSyncLogs(): HasMany
    {
        return $this->hasMany(ProductSyncLog::class, 'merchant_id');
    }

    /* ----------------------------------------------------------------
     |  辅助方法
     | ---------------------------------------------------------------- */

    /**
     * 判断商户是否启用
     */
    public function isActive(): bool
    {
        return $this->status === 1;
    }

    /**
     * 判断商户是否被锁定
     */
    public function isLocked(): bool
    {
        return $this->locked_until !== null && $this->locked_until->isFuture();
    }
}
