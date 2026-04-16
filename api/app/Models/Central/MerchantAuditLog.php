<?php

namespace App\Models\Central;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * 商户审核日志模型 — Central DB
 *
 * 对应表：merchant_audit_logs（central 库）
 * 记录商户审核全过程：审核通过、拒绝、补充信息请求等。
 *
 * @property int         $id
 * @property int         $merchant_id
 * @property int|null    $admin_id
 * @property string      $action        register|approve|reject|request_info|status_change|level_change
 * @property string|null $from_status   变更前状态
 * @property string|null $to_status     变更后状态
 * @property string|null $comment       审核意见/拒绝原因
 * @property array|null  $metadata      额外元数据
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 */
class MerchantAuditLog extends CentralModel
{
    protected $table = 'merchant_audit_logs';

    protected $fillable = [
        'merchant_id',
        'admin_id',
        'action',
        'from_status',
        'to_status',
        'comment',
        'metadata',
    ];

    protected $casts = [
        'metadata' => 'array',
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
     * 操作管理员（可为 null，表示系统自动操作）
     */
    public function admin(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'admin_id');
    }

    /* ----------------------------------------------------------------
     |  工厂方法（便捷创建）
     | ---------------------------------------------------------------- */

    /**
     * 快速记录一条审核日志
     *
     * @param  int         $merchantId
     * @param  string      $action      操作类型
     * @param  string|null $fromStatus  变更前状态
     * @param  string|null $toStatus    变更后状态
     * @param  int|null    $adminId     操作管理员 ID
     * @param  string|null $comment     审核意见
     * @param  array|null  $metadata    额外数据
     * @return static
     */
    public static function record(
        int $merchantId,
        string $action,
        ?string $fromStatus = null,
        ?string $toStatus = null,
        ?int $adminId = null,
        ?string $comment = null,
        ?array $metadata = null
    ): static {
        return static::create([
            'merchant_id' => $merchantId,
            'admin_id'    => $adminId,
            'action'      => $action,
            'from_status' => $fromStatus,
            'to_status'   => $toStatus,
            'comment'     => $comment,
            'metadata'    => $metadata,
        ]);
    }
}
