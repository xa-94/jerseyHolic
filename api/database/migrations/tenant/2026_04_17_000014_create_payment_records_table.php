<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 支付记录
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('order_id')->comment('订单ID');
            $table->string('payment_no', 64)->comment('支付流水号');
            $table->unsignedBigInteger('account_id')->default(0)->comment('支付账号ID(引用Central DB payment_accounts表)');
            $table->tinyInteger('channel')->default(0)->comment('支付渠道(PaymentChannel枚举值)');
            $table->decimal('amount', 12, 2)->default(0.00)->comment('支付金额(USD)');
            $table->decimal('original_amount', 12, 2)->default(0.00)->comment('原始货币金额');
            $table->string('currency', 10)->default('USD')->comment('原始货币');
            $table->string('external_order_id', 128)->default('')->comment('外部订单号(PayPal/Stripe)');
            $table->string('external_transaction_id', 128)->default('')->comment('外部交易号');
            $table->tinyInteger('status')->default(0)->comment('状态: 0=待支付, 1=成功, 2=失败, 3=已取消, 4=处理中');
            $table->string('payer_email', 128)->default('')->comment('付款人邮箱');
            $table->string('payer_id', 64)->default('')->comment('付款人ID');
            $table->json('raw_response')->nullable()->comment('支付网关原始响应JSON');
            $table->string('failure_reason', 500)->default('')->comment('失败原因');
            $table->timestamps();

            $table->unique('payment_no', 'udx_payments_payment_no');
            $table->index('order_id', 'idx_payments_order_id');
            $table->index('account_id', 'idx_payments_account_id');
            $table->index('external_order_id', 'idx_payments_external_order_id');
            $table->index('external_transaction_id', 'idx_payments_external_txn_id');
            $table->index('status', 'idx_payments_status');
        });

        // 支付交易流水
        Schema::create('payment_transactions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('payment_id')->comment('支付记录ID');
            $table->unsignedBigInteger('order_id')->comment('订单ID');
            $table->string('transaction_id', 128)->comment('交易ID');
            $table->string('type', 32)->comment('交易类型: authorize/capture/refund/void');
            $table->decimal('amount', 12, 2)->default(0.00)->comment('交易金额(USD)');
            $table->string('currency', 10)->default('USD')->comment('货币');
            $table->tinyInteger('status')->default(0)->comment('状态: 0=待处理, 1=成功, 2=失败');
            $table->json('raw_data')->nullable()->comment('原始交易数据');
            $table->timestamps();

            $table->index('payment_id', 'idx_payment_transactions_payment_id');
            $table->index('order_id', 'idx_payment_transactions_order_id');
            $table->index('transaction_id', 'idx_payment_transactions_txn_id');
        });

        // 退款记录
        Schema::create('refunds', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('order_id')->comment('订单ID');
            $table->unsignedBigInteger('payment_id')->default(0)->comment('支付记录ID');
            $table->string('refund_no', 64)->comment('退款单号');
            $table->string('external_refund_id', 128)->default('')->comment('外部退款ID');
            $table->decimal('amount', 12, 2)->default(0.00)->comment('退款金额(USD)');
            $table->string('currency', 10)->default('USD')->comment('货币');
            $table->string('reason', 255)->default('')->comment('退款原因');
            $table->tinyInteger('type')->default(1)->comment('退款类型: 1=全额退款, 2=部分退款');
            $table->tinyInteger('status')->default(0)->comment('状态: 0=待处理, 1=退款中, 2=已退款, 3=失败');
            $table->string('operator', 64)->default('')->comment('操作人');
            $table->json('raw_response')->nullable()->comment('退款接口原始响应');
            $table->timestamp('refunded_at')->nullable()->comment('退款完成时间');
            $table->timestamps();

            $table->unique('refund_no', 'udx_refunds_refund_no');
            $table->index('order_id', 'idx_refunds_order_id');
            $table->index('status', 'idx_refunds_status');
        });

        // 争议记录
        Schema::create('disputes', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('order_id')->comment('订单ID');
            $table->string('dispute_id', 128)->comment('PayPal争议ID');
            $table->string('reason', 255)->default('')->comment('争议原因');
            $table->string('dispute_type', 64)->default('')->comment('争议类型: INQUIRY/CHARGEBACK');
            $table->decimal('amount', 12, 2)->default(0.00)->comment('争议金额(USD)');
            $table->string('currency', 10)->default('USD')->comment('货币');
            $table->tinyInteger('status')->default(1)->comment('状态: 1=待处理, 2=处理中, 3=已解决, 4=败诉');
            $table->string('outcome', 64)->default('')->comment('结果: RESOLVED_BUYER_FAVOUR/RESOLVED_SELLER_FAVOUR等');
            $table->json('messages')->nullable()->comment('争议沟通记录JSON');
            $table->json('evidence')->nullable()->comment('提交的证据JSON');
            $table->string('seller_response', 32)->default('')->comment('卖家响应: ACCEPT_CLAIM/PROVIDE_EVIDENCE等');
            $table->timestamp('resolved_at')->nullable()->comment('解决时间');
            $table->timestamps();

            $table->index('order_id', 'idx_disputes_order_id');
            $table->unique('dispute_id', 'udx_disputes_dispute_id');
            $table->index('status', 'idx_disputes_status');
        });

        // 信用卡临时信息
        Schema::create('payment_cards', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('order_id')->comment('订单ID');
            $table->string('card_name', 128)->default('')->comment('持卡人姓名');
            $table->string('card_number_masked', 32)->default('')->comment('卡号掩码(仅存后4位)');
            $table->string('card_brand', 32)->default('')->comment('卡品牌: visa/mastercard/amex');
            $table->string('expiry', 8)->default('')->comment('有效期 MM/YY');
            $table->string('token', 255)->default('')->comment('支付网关Token(非真实卡号)');
            $table->tinyInteger('is_3ds')->default(0)->comment('是否3DS验证: 0=否, 1=是');
            $table->timestamps();

            $table->index('order_id', 'idx_payment_cards_order_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_cards');
        Schema::dropIfExists('disputes');
        Schema::dropIfExists('refunds');
        Schema::dropIfExists('payment_transactions');
        Schema::dropIfExists('payments');
    }
};
