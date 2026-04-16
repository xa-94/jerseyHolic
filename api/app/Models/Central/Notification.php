<?php

namespace App\Models\Central;

use Illuminate\Database\Eloquent\Builder;

/**
 * 站内通知模型 — Central DB
 *
 * 对应表：jh_notifications（central 库）
 * 支持向 Admin 和 Merchant 发送不同类型的通知。
 *
 * @property int              $id
 * @property string           $user_type
 * @property int              $user_id
 * @property string           $type
 * @property string           $title
 * @property string           $content
 * @property string           $channel
 * @property int              $is_read
 * @property \Carbon\Carbon|null $read_at
 * @property array|null       $metadata
 * @property \Carbon\Carbon   $created_at
 * @property \Carbon\Carbon   $updated_at
 */
class Notification extends CentralModel
{
    protected $table = 'notifications';

    /** 用户类型 */
    public const USER_TYPE_ADMIN    = 'admin';
    public const USER_TYPE_MERCHANT = 'merchant';

    /** 通知类型 */
    public const TYPE_RISK_ALERT    = 'risk_alert';
    public const TYPE_SETTLEMENT    = 'settlement';
    public const TYPE_ACCOUNT_ISSUE = 'account_issue';
    public const TYPE_BLACKLIST     = 'blacklist';

    /** 通知渠道 */
    public const CHANNEL_SITE     = 'site';
    public const CHANNEL_DINGTALK = 'dingtalk';

    /** 已读状态 */
    public const UNREAD = 0;
    public const READ   = 1;

    protected $fillable = [
        'user_type',
        'user_id',
        'type',
        'title',
        'content',
        'channel',
        'is_read',
        'read_at',
        'metadata',
    ];

    protected $casts = [
        'user_id'  => 'integer',
        'is_read'  => 'integer',
        'read_at'  => 'datetime',
        'metadata' => 'array',
    ];

    /* ----------------------------------------------------------------
     |  Scopes
     | ---------------------------------------------------------------- */

    /**
     * 按用户筛选通知
     */
    public function scopeForUser(Builder $query, string $userType, int $userId): Builder
    {
        return $query->where('user_type', $userType)->where('user_id', $userId);
    }

    /**
     * 仅未读通知
     */
    public function scopeUnread(Builder $query): Builder
    {
        return $query->where('is_read', self::UNREAD);
    }

    /**
     * 按通知类型筛选
     */
    public function scopeOfType(Builder $query, string $type): Builder
    {
        return $query->where('type', $type);
    }

    /* ----------------------------------------------------------------
     |  辅助方法
     | ---------------------------------------------------------------- */

    /**
     * 标记为已读
     */
    public function markAsRead(): bool
    {
        return $this->update([
            'is_read' => self::READ,
            'read_at' => now(),
        ]);
    }

    /**
     * 是否未读
     */
    public function isUnread(): bool
    {
        return $this->is_read === self::UNREAD;
    }
}
