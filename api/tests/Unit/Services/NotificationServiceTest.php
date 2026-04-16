<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Models\Central\Admin;
use App\Models\Central\Notification;
use App\Services\NotificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class NotificationServiceTest extends TestCase
{
    use RefreshDatabase;

    private NotificationService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new NotificationService();
    }

    /* ----------------------------------------------------------------
     |  发送站内通知成功
     | ---------------------------------------------------------------- */

    public function test_send_notification_creates_database_record(): void
    {
        $notification = $this->service->send(
            recipientType: Notification::USER_TYPE_ADMIN,
            recipientId: 1,
            title: 'Test Notification',
            content: 'This is a test notification',
            type: NotificationService::TYPE_SETTLEMENT,
        );

        $this->assertInstanceOf(Notification::class, $notification);
        $this->assertSame('Test Notification', $notification->title);
        $this->assertSame(Notification::UNREAD, $notification->is_read);
    }

    /* ----------------------------------------------------------------
     |  发送给管理员（broadcast）
     | ---------------------------------------------------------------- */

    public function test_send_to_admin_broadcasts_to_all_admins(): void
    {
        // 创建测试管理员
        Admin::create(['id' => 1, 'username' => 'admin1', 'password' => bcrypt('pw'), 'status' => 1]);
        Admin::create(['id' => 2, 'username' => 'admin2', 'password' => bcrypt('pw'), 'status' => 1]);

        $this->service->sendToAdmin(
            title: 'Broadcast Alert',
            content: 'Alert content',
            type: NotificationService::TYPE_RISK,
        );

        $count = Notification::where('type', NotificationService::TYPE_RISK)
            ->where('user_type', Notification::USER_TYPE_ADMIN)
            ->count();

        $this->assertSame(2, $count);
    }

    /* ----------------------------------------------------------------
     |  发送给商户
     | ---------------------------------------------------------------- */

    public function test_send_to_merchant(): void
    {
        $this->service->sendToMerchant(
            merchantId: 99,
            title: 'Merchant Notice',
            content: 'Your settlement is ready',
            type: NotificationService::TYPE_SETTLEMENT,
        );

        $notification = Notification::where('user_type', Notification::USER_TYPE_MERCHANT)
            ->where('user_id', 99)
            ->first();

        $this->assertNotNull($notification);
        $this->assertSame('Merchant Notice', $notification->title);
    }

    /* ----------------------------------------------------------------
     |  标记已读
     | ---------------------------------------------------------------- */

    public function test_mark_notification_as_read(): void
    {
        $notification = $this->service->send(
            recipientType: Notification::USER_TYPE_ADMIN,
            recipientId: 1,
            title: 'Unread',
            content: 'Will be read',
            type: NotificationService::TYPE_ACCOUNT,
        );

        $this->assertTrue($notification->isUnread());

        $notification->markAsRead();
        $notification->refresh();

        $this->assertSame(Notification::READ, $notification->is_read);
        $this->assertNotNull($notification->read_at);
    }

    /* ----------------------------------------------------------------
     |  钉钉 Webhook 发送（Mock HTTP）
     | ---------------------------------------------------------------- */

    public function test_dingtalk_webhook_send(): void
    {
        config(['notification.dingtalk.webhook_url' => 'https://oapi.dingtalk.com/robot/send?access_token=test']);

        Http::fake([
            'oapi.dingtalk.com/*' => Http::response(['errcode' => 0, 'errmsg' => 'ok'], 200),
        ]);

        $result = $this->service->pushDingtalk('Test Alert', 'Alert content', 'warning');

        $this->assertTrue($result);
        Http::assertSent(fn($request) => str_contains($request->url(), 'oapi.dingtalk.com'));
    }

    /* ----------------------------------------------------------------
     |  批量通知
     | ---------------------------------------------------------------- */

    public function test_batch_notifications(): void
    {
        for ($i = 1; $i <= 5; $i++) {
            $this->service->send(
                recipientType: Notification::USER_TYPE_MERCHANT,
                recipientId: 100,
                title: "Notification {$i}",
                content: "Content {$i}",
                type: NotificationService::TYPE_PAYMENT,
            );
        }

        $count = Notification::where('user_type', Notification::USER_TYPE_MERCHANT)
            ->where('user_id', 100)
            ->count();

        $this->assertSame(5, $count);
    }

    /* ----------------------------------------------------------------
     |  未读数统计
     | ---------------------------------------------------------------- */

    public function test_unread_count(): void
    {
        // 创建 3 条未读 + 1 条已读
        for ($i = 0; $i < 3; $i++) {
            $this->service->send(
                recipientType: Notification::USER_TYPE_ADMIN,
                recipientId: 1,
                title: "Unread {$i}",
                content: "Content",
                type: NotificationService::TYPE_ACCOUNT,
            );
        }

        $readNotification = $this->service->send(
            recipientType: Notification::USER_TYPE_ADMIN,
            recipientId: 1,
            title: 'Read',
            content: 'Already read',
            type: NotificationService::TYPE_ACCOUNT,
        );
        $readNotification->markAsRead();

        $unreadCount = Notification::forUser(Notification::USER_TYPE_ADMIN, 1)
            ->unread()
            ->count();

        $this->assertSame(3, $unreadCount);
    }
}
