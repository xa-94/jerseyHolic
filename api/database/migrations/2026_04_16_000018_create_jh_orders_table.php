<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('jh_orders', function (Blueprint $table) {
            $table->id();
            $table->string('order_no', 64)->comment('系统订单号');
            $table->string('a_order_no', 64)->default('')->comment('A站(OpenCart)订单号');
            $table->string('yy_order_id', 64)->default('')->comment('YY平台订单号');
            $table->unsignedBigInteger('legacy_oc_order_id')->default(0)->comment('旧OC系统订单ID(数据迁移溯源)');
            $table->unsignedBigInteger('legacy_tp_order_id')->default(0)->comment('旧TP系统订单ID(数据迁移溯源)');
            $table->unsignedBigInteger('customer_id')->default(0)->comment('买家ID，0=游客');
            $table->unsignedBigInteger('merchant_id')->default(0)->comment('商户ID');
            $table->string('a_website', 255)->default('')->comment('来源域名');
            $table->string('domain', 255)->default('')->comment('店铺域名');
            // 金额字段
            $table->decimal('a_price', 12, 2)->default(0.00)->comment('订单原始金额(原始货币)');
            $table->string('currency', 10)->default('USD')->comment('订单货币');
            $table->decimal('exchange_rate', 15, 8)->default(1.00000000)->comment('下单时锁定汇率');
            $table->decimal('price', 12, 2)->default(0.00)->comment('订单金额(USD)');
            $table->decimal('shipping_fee', 12, 2)->default(0.00)->comment('运费(USD)');
            $table->decimal('tax_amount', 12, 2)->default(0.00)->comment('税费(USD)');
            $table->decimal('discount_amount', 12, 2)->default(0.00)->comment('折扣金额(USD)');
            $table->decimal('total', 12, 2)->default(0.00)->comment('订单总计(USD)');
            // 状态字段
            $table->tinyInteger('pay_status')->default(1)->comment('支付状态: 1=待支付,2=支付失败,3=已支付,4=已取消,5=部分退款,6=已退款,7=交易中,8=部分退款中,9=退款中');
            $table->tinyInteger('shipment_status')->default(0)->comment('发货状态: 0=未处理,1=待配货,3=配货中,8=配货完成,9=物流已揽收');
            $table->tinyInteger('refund_status')->default(1)->comment('退款状态: 1=未退款,5=部分退款,6=已退款,8=部分退款中,9=退款中');
            $table->tinyInteger('dispute_status')->default(1)->comment('纠纷状态: 1=无纠纷,2=有纠纷,3=纠纷结束');
            // 支付信息
            $table->tinyInteger('pay_type')->default(0)->comment('支付类型: 0=Other,1=PayPal,...,16=Starlink (见PaymentChannel枚举)');
            $table->timestamp('pay_time')->nullable()->comment('支付时间');
            $table->string('paypal_account', 128)->default('')->comment('使用的PayPal账号标识');
            $table->string('paypal_email', 128)->default('')->comment('PayPal收款邮箱');
            $table->string('paypal_order_id', 128)->default('')->comment('PayPal订单ID');
            $table->string('paypal_transaction_no', 128)->default('')->comment('PayPal交易流水号');
            $table->string('stripe_client', 128)->default('')->comment('Stripe Client标识');
            $table->string('stripe_session_id', 255)->default('')->comment('Stripe Session ID');
            // 客户信息冗余
            $table->string('customer_email', 128)->default('')->comment('客户邮箱');
            $table->string('customer_name', 128)->default('')->comment('客户姓名');
            $table->string('customer_phone', 32)->default('')->comment('客户电话');
            // 标记字段
            $table->tinyInteger('is_blacklist')->default(0)->comment('是否黑名单: 0=否, 1=是');
            $table->tinyInteger('is_zw')->default(0)->comment('是否含仿牌(hic): 0=否, 1=是');
            $table->tinyInteger('is_diy')->default(0)->comment('是否含定制(DIY): 0=否, 1=是');
            $table->tinyInteger('is_wpz')->default(0)->comment('是否含正品(WPZ): 0=否, 1=是');
            $table->tinyInteger('risk_type')->default(1)->comment('风险等级: 1=低,2=中,3=高');
            $table->tinyInteger('deduction_status')->default(0)->comment('扣款状态: 0=未扣款, 1=已扣款');
            $table->tinyInteger('settlement_status')->default(0)->comment('结算状态: 0=未结算, 1=已结算');
            $table->string('coupon_code', 64)->default('')->comment('使用的优惠券码');
            $table->string('ip', 45)->default('')->comment('下单IP');
            $table->string('user_agent', 500)->default('')->comment('下单User-Agent');
            $table->text('remark')->nullable()->comment('订单备注');
            $table->timestamps();
            $table->softDeletes();

            $table->unique('order_no', 'udx_orders_order_no');
            $table->index('a_order_no', 'idx_orders_a_order_no');
            $table->index('customer_id', 'idx_orders_customer_id');
            $table->index('merchant_id', 'idx_orders_merchant_id');
            $table->index('pay_status', 'idx_orders_pay_status');
            $table->index('shipment_status', 'idx_orders_shipment_status');
            $table->index('pay_type', 'idx_orders_pay_type');
            $table->index('domain', 'idx_orders_domain');
            $table->index('customer_email', 'idx_orders_customer_email');
            $table->index('paypal_order_id', 'idx_orders_paypal_order_id');
            $table->index('paypal_transaction_no', 'idx_orders_paypal_txn_no');
            $table->index('created_at', 'idx_orders_created_at');
            $table->index('is_blacklist', 'idx_orders_is_blacklist');
            $table->index(['pay_status', 'created_at'], 'idx_orders_pay_status_created');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('jh_orders');
    }
};
