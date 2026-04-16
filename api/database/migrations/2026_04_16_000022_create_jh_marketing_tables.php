<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 优惠券
        Schema::create('jh_coupons', function (Blueprint $table) {
            $table->id();
            $table->string('code', 64)->comment('优惠券码');
            $table->string('name', 128)->default('')->comment('优惠券名称');
            $table->string('type', 32)->default('fixed')->comment('类型: fixed=固定金额, percentage=百分比, free_shipping=免运费');
            $table->decimal('discount', 12, 2)->default(0.00)->comment('折扣值(金额或百分比)');
            $table->decimal('minimum_amount', 12, 2)->default(0.00)->comment('最低消费金额(USD)');
            $table->integer('uses_total')->default(0)->comment('总使用次数上限，0=不限');
            $table->integer('uses_customer')->default(0)->comment('每客户使用次数上限，0=不限');
            $table->integer('used_count')->default(0)->comment('已使用次数');
            $table->timestamp('start_at')->nullable()->comment('有效期开始');
            $table->timestamp('end_at')->nullable()->comment('有效期结束');
            $table->tinyInteger('status')->default(1)->comment('状态: 0=禁用, 1=启用');
            $table->timestamps();

            $table->unique('code', 'udx_coupons_code');
            $table->index('status', 'idx_coupons_status');
            $table->index(['start_at', 'end_at'], 'idx_coupons_date_range');
        });

        // 优惠券使用记录
        Schema::create('jh_coupon_usage', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('coupon_id')->comment('优惠券ID');
            $table->unsignedBigInteger('order_id')->comment('订单ID');
            $table->unsignedBigInteger('customer_id')->default(0)->comment('买家ID');
            $table->decimal('discount_amount', 12, 2)->default(0.00)->comment('实际折扣金额');
            $table->timestamps();

            $table->index('coupon_id', 'idx_coupon_usage_coupon_id');
            $table->index('customer_id', 'idx_coupon_usage_customer_id');
            $table->index('order_id', 'idx_coupon_usage_order_id');
        });

        // 促销活动
        Schema::create('jh_promotions', function (Blueprint $table) {
            $table->id();
            $table->string('name', 128)->comment('促销名称');
            $table->string('type', 32)->default('percentage')->comment('类型: percentage/fixed/buy_x_get_y');
            $table->decimal('discount_value', 12, 2)->default(0.00)->comment('折扣值');
            $table->json('conditions')->nullable()->comment('促销条件JSON(商品/分类/客户组等)');
            $table->timestamp('start_at')->nullable()->comment('开始时间');
            $table->timestamp('end_at')->nullable()->comment('结束时间');
            $table->integer('priority')->default(0)->comment('优先级');
            $table->tinyInteger('status')->default(1)->comment('状态: 0=禁用, 1=启用');
            $table->timestamps();

            $table->index('status', 'idx_promotions_status');
            $table->index(['start_at', 'end_at'], 'idx_promotions_date_range');
        });

        // 积分记录
        Schema::create('jh_reward_points', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('customer_id')->comment('买家ID');
            $table->unsignedBigInteger('order_id')->default(0)->comment('关联订单ID');
            $table->integer('points')->default(0)->comment('积分变动(正=增加, 负=扣减)');
            $table->string('description', 255)->default('')->comment('变动说明');
            $table->string('type', 32)->default('order')->comment('类型: order/manual/redeem/expire');
            $table->timestamps();

            $table->index('customer_id', 'idx_reward_points_customer_id');
            $table->index('order_id', 'idx_reward_points_order_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('jh_reward_points');
        Schema::dropIfExists('jh_promotions');
        Schema::dropIfExists('jh_coupon_usage');
        Schema::dropIfExists('jh_coupons');
    }
};
