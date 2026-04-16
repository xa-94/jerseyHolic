<?php

namespace App\Models\Central;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Stancl\Tenancy\Contracts\Tenant as TenantContract;
use Stancl\Tenancy\Database\Concerns\CentralConnection;
use Stancl\Tenancy\Database\Concerns\GeneratesIds;
use Stancl\Tenancy\Database\Concerns\HasDatabase;
use Stancl\Tenancy\Database\Concerns\HasDomains;
use Stancl\Tenancy\Database\Concerns\InvalidatesResolverCache;
use Stancl\Tenancy\Database\Concerns\TenantRun;
use Stancl\Tenancy\Database\Models\Tenant;
use Stancl\Tenancy\Events;

/**
 * 店铺/站点模型 — Central DB (stancl/tenancy Tenant Model)
 *
 * 对应表：stores（central 库）
 * 每个 Store 实例代表一个独立的租户（Tenant），拥有独立的 Tenant DB。
 * 通过 stancl/tenancy 的 Tenant 契约管理数据库生命周期。
 *
 * @property int    $id
 * @property int    $merchant_id
 * @property string $store_name
 * @property string $store_code
 * @property string|null $domain
 * @property int    $status
 * @property string|null $database_name
 * @property string|null $database_password
 * @property array|null  $target_markets
 * @property array|null  $supported_languages
 * @property array|null  $supported_currencies
 * @property array|null  $product_categories
 * @property array|null  $payment_preferences
 * @property array|null  $logistics_config
 * @property array|null  $theme_config
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 * @property \Carbon\Carbon|null $deleted_at
 */
class Store extends Tenant
{
    use SoftDeletes;

    protected $table = 'stores';

    /**
     * stancl/tenancy 要求的 Tenant key 字段名
     */
    public function getTenantKeyName(): string
    {
        return 'id';
    }

    /**
     * stancl/tenancy 要求的 Tenant key 值
     */
    public function getTenantKey(): mixed
    {
        return $this->getAttribute($this->getTenantKeyName());
    }

    protected $fillable = [
        'merchant_id',
        'store_name',
        'store_code',
        'domain',
        'status',
        'database_name',
        'database_password',
        'target_markets',
        'supported_languages',
        'supported_currencies',
        'product_categories',
        'payment_preferences',
        'logistics_config',
        'theme_config',
    ];

    protected $hidden = [
        'database_password',
    ];

    protected $casts = [
        'merchant_id'           => 'integer',
        'status'                => 'integer',
        'database_password'     => 'encrypted',
        'target_markets'        => 'array',
        'supported_languages'   => 'array',
        'supported_currencies'  => 'array',
        'product_categories'    => 'array',
        'payment_preferences'   => 'array',
        'logistics_config'      => 'array',
        'theme_config'          => 'array',
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

    /**
     * 店铺绑定的域名列表
     */
    public function domains(): HasMany
    {
        return $this->hasMany(Domain::class, 'store_id');
    }

    /**
     * 店铺关联的支付账号（多对多，通过中间表 store_payment_accounts）
     */
    public function paymentAccounts(): BelongsToMany
    {
        return $this->belongsToMany(
            PaymentAccount::class,
            'store_payment_accounts',
            'store_id',
            'payment_account_id'
        )->withPivot(['priority', 'is_active'])->withTimestamps();
    }

    /* ----------------------------------------------------------------
     |  辅助方法
     | ---------------------------------------------------------------- */

    /**
     * 判断店铺是否启用
     */
    public function isActive(): bool
    {
        return $this->status === 1;
    }
}
