<?php

namespace App\Models\Central;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * 支付账号模型 — Central DB
 *
 * 对应表：jh_payment_accounts（central 库）
 * 全局支付账号池，通过 store_payment_accounts 中间表关联到各店铺。
 *
 * @property int    $id
 * @property string $account
 * @property string $email
 * @property string $client_id
 * @property string $client_secret
 * @property string $merchant_id_external
 * @property string $pay_method
 * @property int    $category_id
 * @property int    $cc_category_id
 * @property int    $status
 * @property int    $permission
 * @property float  $min_money
 * @property float  $max_money
 * @property float  $limit_money
 * @property float  $daily_limit_money
 * @property float  $money_total
 * @property float  $daily_money_total
 * @property int    $priority
 * @property int    $max_num
 * @property int    $deal_count
 * @property int    $is_new
 * @property int    $is_force
 * @property \Carbon\Carbon|null $error_time
 * @property string $error_msg
 * @property string $webhook_id
 * @property string|null $access_token
 * @property \Carbon\Carbon|null $access_token_expires_at
 * @property string $success_url
 * @property string $cancel_url
 * @property string $pay_url
 * @property string $domain
 * @property \Carbon\Carbon|null $daily_reset_date
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 * @property \Carbon\Carbon|null $deleted_at
 */
class PaymentAccount extends CentralModel
{
    use SoftDeletes;

    protected $table = 'jh_payment_accounts';

    protected $fillable = [
        'account',
        'email',
        'client_id',
        'client_secret',
        'merchant_id_external',
        'pay_method',
        'category_id',
        'cc_category_id',
        'status',
        'permission',
        'min_money',
        'max_money',
        'limit_money',
        'daily_limit_money',
        'money_total',
        'daily_money_total',
        'priority',
        'max_num',
        'deal_count',
        'is_new',
        'is_force',
        'error_time',
        'error_msg',
        'webhook_id',
        'access_token',
        'access_token_expires_at',
        'success_url',
        'cancel_url',
        'pay_url',
        'domain',
        'daily_reset_date',
    ];

    protected $hidden = [
        'client_secret',
        'access_token',
    ];

    protected $casts = [
        'category_id'            => 'integer',
        'cc_category_id'         => 'integer',
        'status'                 => 'integer',
        'permission'             => 'integer',
        'min_money'              => 'decimal:2',
        'max_money'              => 'decimal:2',
        'limit_money'            => 'decimal:2',
        'daily_limit_money'      => 'decimal:2',
        'money_total'            => 'decimal:2',
        'daily_money_total'      => 'decimal:2',
        'priority'               => 'integer',
        'max_num'                => 'integer',
        'deal_count'             => 'integer',
        'is_new'                 => 'integer',
        'is_force'               => 'integer',
        'error_time'             => 'datetime',
        'access_token_expires_at' => 'datetime',
        'daily_reset_date'       => 'date',
    ];

    /* ----------------------------------------------------------------
     |  关系
     | ---------------------------------------------------------------- */

    /**
     * 关联的店铺（多对多）
     */
    public function stores(): BelongsToMany
    {
        return $this->belongsToMany(
            Store::class,
            'store_payment_accounts',
            'payment_account_id',
            'store_id'
        )->withPivot(['priority', 'is_active'])->withTimestamps();
    }

    public function group(): BelongsTo
    {
        return $this->belongsTo(PaymentAccountGroup::class, 'category_id');
    }

    public function ccGroup(): BelongsTo
    {
        return $this->belongsTo(PaymentAccountGroup::class, 'cc_category_id');
    }

    public function logs(): HasMany
    {
        return $this->hasMany(PaymentAccountLog::class, 'account_id');
    }

    /* ----------------------------------------------------------------
     |  Scopes
     | ---------------------------------------------------------------- */

    /**
     * 仅启用且可收款的账号
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', 1)->where('permission', 1);
    }

    /**
     * 按 PayPal 分组筛选
     */
    public function scopeByGroup(Builder $query, int $groupId): Builder
    {
        return $query->where('category_id', $groupId);
    }

    /**
     * 按信用卡分组筛选
     */
    public function scopeByCcGroup(Builder $query, int $groupId): Builder
    {
        return $query->where('cc_category_id', $groupId);
    }

    public function scopePayMethod($query, string $method)
    {
        return $query->where('pay_method', $method);
    }

    /* ----------------------------------------------------------------
     |  辅助方法
     | ---------------------------------------------------------------- */

    /**
     * 判断账号是否健康（启用、可收款、无异常）
     */
    public function isHealthy(): bool
    {
        return $this->status === 1
            && $this->permission === 1
            && empty($this->error_msg);
    }

    /**
     * 判断账号是否可接受指定金额
     */
    public function canAcceptAmount(float $amount): bool
    {
        if ($amount < (float) $this->min_money || $amount > (float) $this->max_money) {
            return false;
        }
        if ((float) $this->limit_money > 0 && (float) $this->money_total + $amount > (float) $this->limit_money) {
            return false;
        }
        if ((float) $this->daily_limit_money > 0 && (float) $this->daily_money_total + $amount > (float) $this->daily_limit_money) {
            return false;
        }
        if ($this->max_num > 0 && $this->deal_count >= $this->max_num) {
            return false;
        }
        return true;
    }

    public function hasError(): bool
    {
        return $this->error_time !== null;
    }
}
