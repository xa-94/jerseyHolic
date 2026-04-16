<?php

namespace App\Services;

use App\Models\Central\Admin;
use App\Models\Central\Notification;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * 消息推送服务（M3-022 / US-PAY-006）
 *
 * 统一的站内通知 + 钉钉 Webhook 推送服务。
 * 支持向 Admin 和 Merchant 发送不同类型、不同级别的通知。
 *
 * 触发场景：
 *  - settlement  — 结算相关
 *  - risk        — 风险告警
 *  - blacklist   — 黑名单触发
 *  - account     — 账号状态变更
 *  - payment     — 支付异常
 */
class NotificationService
{
    /* ----------------------------------------------------------------
     |  通知类型常量
     | ---------------------------------------------------------------- */

    public const TYPE_SETTLEMENT = 'settlement';
    public const TYPE_RISK       = 'risk';
    public const TYPE_BLACKLIST  = 'blacklist';
    public const TYPE_ACCOUNT    = 'account';
    public const TYPE_PAYMENT    = 'payment';

    /* ----------------------------------------------------------------
     |  通知级别常量
     | ---------------------------------------------------------------- */

    public const LEVEL_INFO    = 'info';
    public const LEVEL_WARNING = 'warning';
    public const LEVEL_ERROR   = 'error';

    /* ----------------------------------------------------------------
     |  核心方法
     | ---------------------------------------------------------------- */

    /**
     * 发送通知（通用入口）
     *
     * @param  string      $recipientType  接收者类型（admin / merchant）
     * @param  int         $recipientId    接收者 ID
     * @param  string      $title          通知标题
     * @param  string      $content        通知内容
     * @param  string      $type           通知类型（见 TYPE_* 常量）
     * @param  string      $level          通知级别（info / warning / error）
     * @param  array       $channels       发送渠道（database / dingtalk）
     * @param  array|null  $metadata       附加元数据
     * @return Notification
     */
    public function send(
        string $recipientType,
        int    $recipientId,
        string $title,
        string $content,
        string $type,
        string $level = self::LEVEL_INFO,
        array  $channels = ['database'],
        ?array $metadata = null,
    ): Notification {
        // 1. 写入站内通知（database 渠道）
        $notification = Notification::create([
            'user_type' => $recipientType,
            'user_id'   => $recipientId,
            'type'      => $type,
            'title'     => $title,
            'content'   => $content,
            'channel'   => implode(',', $channels),
            'is_read'   => Notification::UNREAD,
            'metadata'  => $metadata,
        ]);

        // 2. 钉钉渠道
        if (in_array('dingtalk', $channels, true)) {
            $this->pushDingtalk($title, $content, $level);
        }

        return $notification;
    }

    /**
     * 发送通知给所有管理员
     *
     * @param  string $title    通知标题
     * @param  string $content  通知内容
     * @param  string $type     通知类型
     * @param  string $level    通知级别
     * @return void
     */
    public function sendToAdmin(
        string $title,
        string $content,
        string $type,
        string $level = self::LEVEL_INFO,
    ): void {
        $adminIds = Admin::query()->pluck('id');

        foreach ($adminIds as $adminId) {
            $this->send(
                recipientType: Notification::USER_TYPE_ADMIN,
                recipientId:   $adminId,
                title:         $title,
                content:       $content,
                type:          $type,
                level:         $level,
            );
        }

        // 风险 / 错误级别同时推送钉钉
        if (in_array($level, [self::LEVEL_WARNING, self::LEVEL_ERROR], true)) {
            $this->pushDingtalk($title, $content, $level);
        }
    }

    /**
     * 发送通知给指定商户
     *
     * @param  int    $merchantId  商户 ID
     * @param  string $title       通知标题
     * @param  string $content     通知内容
     * @param  string $type        通知类型
     * @param  string $level       通知级别
     * @return void
     */
    public function sendToMerchant(
        int    $merchantId,
        string $title,
        string $content,
        string $type,
        string $level = self::LEVEL_INFO,
    ): void {
        $this->send(
            recipientType: Notification::USER_TYPE_MERCHANT,
            recipientId:   $merchantId,
            title:         $title,
            content:       $content,
            type:          $type,
            level:         $level,
        );
    }

    /**
     * 钉钉 Webhook 推送
     *
     * 使用 Markdown 格式发送消息，失败自动重试 3 次。
     *
     * @param  string $title    消息标题
     * @param  string $content  消息内容
     * @param  string $level    消息级别
     * @return bool
     */
    public function pushDingtalk(string $title, string $content, string $level = self::LEVEL_INFO): bool
    {
        $webhookUrl = config('notification.dingtalk.webhook_url');

        if (empty($webhookUrl)) {
            Log::warning('[NotificationService] DingTalk webhook URL not configured.');
            return false;
        }

        $payload = [
            'msgtype'  => 'markdown',
            'markdown' => [
                'title' => "[{$level}] {$title}",
                'text'  => "### {$title}\n\n{$content}\n\n> " . now()->format('Y-m-d H:i:s'),
            ],
        ];

        try {
            $response = Http::timeout(10)
                ->retry(3, 500)
                ->post($webhookUrl, $payload);

            if ($response->successful()) {
                Log::info('[NotificationService] DingTalk message sent.', ['title' => $title]);
                return true;
            }

            Log::error('[NotificationService] DingTalk send failed.', [
                'status' => $response->status(),
                'body'   => $response->body(),
            ]);
            return false;
        } catch (\Throwable $e) {
            Log::error('[NotificationService] DingTalk send exception.', [
                'error' => $e->getMessage(),
                'title' => $title,
            ]);
            return false;
        }
    }
}
