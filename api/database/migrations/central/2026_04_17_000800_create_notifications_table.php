<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 站内通知表 — M3 新增
 *
 * 支持向 Admin 和 Merchant 发送不同类型的通知：
 * - risk_alert: 风控预警（争议率/退款率超阈值、黑名单命中等）
 * - settlement: 结算通知（结算单生成、审核通过、打款完成）
 * - account_issue: 账号异常（余额不足、API 调用失败、Token 过期）
 * - blacklist: 黑名单通知（订单命中黑名单被拦截）
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notifications', function (Blueprint $table) {
            $table->engine = 'InnoDB';

            $table->id();
            $table->string('user_type', 16)->comment('用户类型：admin/merchant');
            $table->unsignedBigInteger('user_id')->comment('用户 ID（Admin ID 或 MerchantUser ID）');
            $table->string('type', 32)->comment('通知类型：risk_alert/settlement/account_issue/blacklist');
            $table->string('title', 128)->comment('通知标题');
            $table->text('content')->comment('通知正文（支持 Markdown）');
            $table->string('channel', 16)->default('site')->comment('通知渠道：site=站内, dingtalk=钉钉');
            $table->tinyInteger('is_read')->default(0)->comment('是否已读：0=未读, 1=已读');
            $table->timestamp('read_at')->nullable()->comment('阅读时间');
            $table->json('metadata')->nullable()->comment('扩展数据（关联 merchant_id、settlement_id 等）');
            $table->timestamps();

            // 按用户查询通知
            $table->index(['user_type', 'user_id'], 'idx_user');
            // 按类型筛选
            $table->index('type', 'idx_type');
            // 筛选未读通知
            $table->index('is_read', 'idx_is_read');
            // 按时间排序
            $table->index('created_at', 'idx_created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notifications');
    }
};
