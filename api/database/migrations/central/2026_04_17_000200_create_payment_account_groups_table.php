<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 创建支付账号分组表 — M3
 *
 * 注意：M1 文档提及此表但未实际创建迁移，此处补建。
 * group_type 字段实现四级分组策略：VIP_EXCLUSIVE / STANDARD_SHARED / LITE_SHARED / BLACKLIST_ISOLATED
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_account_groups', function (Blueprint $table) {
            $table->engine = 'InnoDB';

            $table->id();
            $table->string('name', 64)->comment('分组名称');
            $table->string('type', 32)->default('paypal')->comment('支付方式：paypal/credit_card/stripe/antom');
            $table->enum('group_type', [
                'VIP_EXCLUSIVE',
                'STANDARD_SHARED',
                'LITE_SHARED',
                'BLACKLIST_ISOLATED',
            ])->default('STANDARD_SHARED')->comment('分组策略类型');
            $table->string('description', 255)->default('')->comment('分组描述');
            $table->tinyInteger('is_blacklist_group')->default(0)->comment('是否黑名单专用组：0=否, 1=是');
            $table->tinyInteger('status')->default(1)->comment('状态：0=禁用, 1=启用');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_account_groups');
    }
};
