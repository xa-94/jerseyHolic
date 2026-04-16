<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 商户店铺/站点
        Schema::create('jh_merchant_shops', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('merchant_id')->comment('商户ID');
            $table->string('website', 255)->comment('域名');
            $table->string('shop_name', 128)->default('')->comment('店铺名称');
            $table->unsignedBigInteger('group_id')->default(0)->comment('PayPal 收款分组ID');
            $table->unsignedBigInteger('cc_group_id')->default(0)->comment('信用卡收款分组ID');
            $table->string('token', 255)->nullable()->comment('API Token');
            $table->tinyInteger('status')->default(1)->comment('状态: 0=禁用, 1=启用');
            $table->json('payment_config')->nullable()->comment('支付配置JSON');
            $table->timestamps();
            $table->softDeletes();

            $table->index('merchant_id', 'idx_merchant_shops_merchant_id');
            $table->unique('website', 'udx_merchant_shops_website');
        });

        // 商户结算记录
        Schema::create('jh_merchant_settlements', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('merchant_id')->comment('商户ID');
            $table->string('settlement_no', 64)->comment('结算单号');
            $table->decimal('total_amount', 12, 2)->default(0.00)->comment('结算总金额(USD)');
            $table->decimal('commission_amount', 12, 2)->default(0.00)->comment('佣金金额');
            $table->decimal('net_amount', 12, 2)->default(0.00)->comment('净结算金额');
            $table->integer('order_count')->default(0)->comment('订单数量');
            $table->date('period_start')->comment('结算周期开始');
            $table->date('period_end')->comment('结算周期结束');
            $table->tinyInteger('status')->default(0)->comment('状态: 0=待结算, 1=已结算, 2=已付款');
            $table->text('remark')->nullable()->comment('备注');
            $table->timestamp('settled_at')->nullable()->comment('结算时间');
            $table->timestamps();

            $table->index('merchant_id', 'idx_merchant_settlements_merchant_id');
            $table->unique('settlement_no', 'udx_merchant_settlements_no');
            $table->index('status', 'idx_merchant_settlements_status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('jh_merchant_settlements');
        Schema::dropIfExists('jh_merchant_shops');
    }
};
