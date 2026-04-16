<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 支付账号分组
        Schema::create('jh_payment_account_groups', function (Blueprint $table) {
            $table->id();
            $table->string('name', 64)->comment('分组名称');
            $table->string('type', 32)->default('paypal')->comment('分组类型: paypal/credit_card/stripe/antom');
            $table->string('description', 255)->default('')->comment('分组描述');
            $table->tinyInteger('is_blacklist_group')->default(0)->comment('是否黑名单专用组: 0=否, 1=是');
            $table->tinyInteger('status')->default(1)->comment('状态: 0=禁用, 1=启用');
            $table->timestamps();
        });

        // 支付账号池
        Schema::create('jh_payment_accounts', function (Blueprint $table) {
            $table->id();
            $table->string('account', 128)->comment('账号标识/名称');
            $table->string('email', 128)->default('')->comment('PayPal邮箱/Stripe标识');
            $table->string('client_id', 255)->default('')->comment('Client ID');
            $table->string('client_secret', 500)->default('')->comment('Client Secret (加密存储)');
            $table->string('merchant_id_external', 64)->default('')->comment('PayPal商户ID');
            $table->string('pay_method', 32)->default('paypal')->comment('支付方式: paypal/credit_card/stripe/antom/payssion');
            $table->unsignedBigInteger('category_id')->default(0)->comment('PayPal分组ID');
            $table->unsignedBigInteger('cc_category_id')->default(0)->comment('信用卡分组ID');
            $table->tinyInteger('status')->default(1)->comment('状态: 0=禁用, 1=启用');
            $table->tinyInteger('permission')->default(1)->comment('权限: 1=可收款, 2=暂停, 3=已封禁');
            $table->decimal('min_money', 12, 2)->default(0.00)->comment('最小金额(USD)');
            $table->decimal('max_money', 12, 2)->default(99999.00)->comment('最大金额(USD)');
            $table->decimal('limit_money', 12, 2)->default(0.00)->comment('总限额(USD)，0=不限');
            $table->decimal('daily_limit_money', 12, 2)->default(0.00)->comment('日限额(USD)，0=不限');
            $table->decimal('money_total', 12, 2)->default(0.00)->comment('累计收款(USD)');
            $table->decimal('daily_money_total', 12, 2)->default(0.00)->comment('当日累计收款(USD)');
            $table->integer('priority')->default(0)->comment('优先级，数值越大优先级越高');
            $table->integer('max_num')->default(0)->comment('最大成交单数，0=不限');
            $table->integer('deal_count')->default(0)->comment('已成交单数');
            $table->tinyInteger('is_new')->default(0)->comment('是否新账号: 0=否, 1=是');
            $table->tinyInteger('is_force')->default(0)->comment('是否强制启用: 0=否, 1=是');
            $table->timestamp('error_time')->nullable()->comment('首次异常时间');
            $table->string('error_msg', 500)->default('')->comment('异常信息');
            $table->string('webhook_id', 128)->default('')->comment('Webhook ID');
            $table->text('access_token')->nullable()->comment('Access Token');
            $table->timestamp('access_token_expires_at')->nullable()->comment('Token 过期时间');
            $table->string('success_url', 512)->default('')->comment('支付成功回调URL');
            $table->string('cancel_url', 512)->default('')->comment('支付取消回调URL');
            $table->string('pay_url', 512)->default('')->comment('支付网关URL');
            $table->string('domain', 255)->default('')->comment('关联域名');
            $table->date('daily_reset_date')->nullable()->comment('日限额重置日期');
            $table->timestamps();
            $table->softDeletes();

            $table->index('pay_method', 'idx_payment_accounts_pay_method');
            $table->index('category_id', 'idx_payment_accounts_category_id');
            $table->index('cc_category_id', 'idx_payment_accounts_cc_category_id');
            $table->index(['status', 'permission'], 'idx_payment_accounts_status_perm');
            $table->index('priority', 'idx_payment_accounts_priority');
        });

        // 支付账号收款日志
        Schema::create('jh_payment_account_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('account_id')->comment('支付账号ID');
            $table->unsignedBigInteger('order_id')->default(0)->comment('订单ID');
            $table->decimal('amount', 12, 2)->default(0.00)->comment('收款金额(USD)');
            $table->string('currency', 10)->default('USD')->comment('原始货币');
            $table->decimal('original_amount', 12, 2)->default(0.00)->comment('原始金额');
            $table->string('action', 32)->default('receive')->comment('操作: receive=收款, refund=退款');
            $table->timestamps();

            $table->index('account_id', 'idx_payment_account_logs_account_id');
            $table->index('order_id', 'idx_payment_account_logs_order_id');
            $table->index('created_at', 'idx_payment_account_logs_created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('jh_payment_account_logs');
        Schema::dropIfExists('jh_payment_accounts');
        Schema::dropIfExists('jh_payment_account_groups');
    }
};
