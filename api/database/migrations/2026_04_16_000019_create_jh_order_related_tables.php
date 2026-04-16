<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 订单商品明细
        Schema::create('jh_order_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('order_id')->comment('订单ID');
            $table->unsignedBigInteger('product_id')->default(0)->comment('商品ID');
            $table->unsignedBigInteger('product_sku_id')->default(0)->comment('SKU ID');
            $table->string('sku', 64)->default('')->comment('SKU 编码');
            $table->string('name', 255)->comment('商品名称(真实名称)');
            $table->string('safe_name', 255)->default('')->comment('安全映射名称');
            $table->string('image', 255)->default('')->comment('商品图片');
            $table->integer('quantity')->default(1)->comment('购买数量');
            $table->decimal('price', 12, 2)->default(0.00)->comment('单价(USD)');
            $table->decimal('total', 12, 2)->default(0.00)->comment('小计(USD)');
            $table->decimal('weight', 8, 2)->default(0.00)->comment('重量(kg)');
            $table->json('options')->nullable()->comment('商品选项JSON，如 {"color":"Red","size":"XL"}');
            $table->timestamps();

            $table->index('order_id', 'idx_order_items_order_id');
            $table->index('product_id', 'idx_order_items_product_id');
        });

        // 订单地址
        Schema::create('jh_order_addresses', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('order_id')->comment('订单ID');
            $table->string('type', 16)->default('shipping')->comment('地址类型: shipping=收货, billing=账单');
            $table->string('firstname', 64)->default('')->comment('名');
            $table->string('lastname', 64)->default('')->comment('姓');
            $table->string('company', 128)->default('')->comment('公司名');
            $table->string('address_1', 255)->default('')->comment('地址行1');
            $table->string('address_2', 255)->default('')->comment('地址行2');
            $table->string('city', 128)->default('')->comment('城市');
            $table->string('postcode', 16)->default('')->comment('邮编');
            $table->string('country', 128)->default('')->comment('国家名称');
            $table->string('country_code', 2)->default('')->comment('国家代码');
            $table->string('zone', 128)->default('')->comment('州/省名称');
            $table->string('zone_code', 32)->default('')->comment('州/省代码');
            $table->string('phone', 32)->default('')->comment('电话');
            $table->string('email', 128)->default('')->comment('邮箱');
            $table->timestamps();

            $table->index('order_id', 'idx_order_addresses_order_id');
            $table->index(['order_id', 'type'], 'idx_order_addresses_order_type');
        });

        // 订单状态变更历史
        Schema::create('jh_order_histories', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('order_id')->comment('订单ID');
            $table->string('status_type', 32)->comment('状态类型: payment/shipping/refund/dispute');
            $table->tinyInteger('old_status')->default(0)->comment('变更前状态');
            $table->tinyInteger('new_status')->default(0)->comment('变更后状态');
            $table->string('comment', 500)->default('')->comment('变更备注');
            $table->string('operator', 64)->default('system')->comment('操作者: system/admin用户名/webhook');
            $table->tinyInteger('notify_customer')->default(0)->comment('是否通知客户: 0=否, 1=是');
            $table->timestamps();

            $table->index('order_id', 'idx_order_histories_order_id');
            $table->index(['order_id', 'status_type'], 'idx_order_histories_order_type');
        });

        // 订单费用明细
        Schema::create('jh_order_totals', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('order_id')->comment('订单ID');
            $table->string('code', 32)->comment('费用代码: sub_total/tax/shipping/coupon/total');
            $table->string('title', 128)->comment('费用标题');
            $table->decimal('value', 12, 2)->default(0.00)->comment('费用金额(USD)');
            $table->integer('sort_order')->default(0)->comment('排序');
            $table->timestamps();

            $table->index('order_id', 'idx_order_totals_order_id');
        });

        // 订单扩展信息
        Schema::create('jh_order_ext', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('order_id')->comment('订单ID');
            $table->string('success_url', 512)->default('')->comment('支付成功回调URL');
            $table->string('cancel_url', 512)->default('')->comment('支付取消回调URL');
            $table->string('notify_url', 512)->default('')->comment('异步通知URL');
            $table->json('extra_data')->nullable()->comment('额外数据JSON');
            $table->timestamps();

            $table->unique('order_id', 'udx_order_ext_order_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('jh_order_ext');
        Schema::dropIfExists('jh_order_totals');
        Schema::dropIfExists('jh_order_histories');
        Schema::dropIfExists('jh_order_addresses');
        Schema::dropIfExists('jh_order_items');
    }
};
