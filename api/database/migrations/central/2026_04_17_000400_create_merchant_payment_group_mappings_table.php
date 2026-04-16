<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 商户-支付方式-支付分组 三层映射表 — M3 新增
 *
 * 实现「商户 + 支付方式 → 使用哪个支付账号分组」的路由规则。
 * 同一商户同一支付方式只能映射一个分组（唯一索引约束）。
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('merchant_payment_group_mappings', function (Blueprint $table) {
            $table->engine = 'InnoDB';

            $table->id();
            $table->unsignedBigInteger('merchant_id')->comment('商户 ID → merchants.id');
            $table->string('pay_method', 32)->comment('支付方式：paypal/credit_card/stripe/antom');
            $table->unsignedBigInteger('payment_group_id')->comment('支付分组 ID → payment_account_groups.id');
            $table->integer('priority')->default(0)->comment('优先级，数值越大越优先');
            $table->timestamps();

            // 唯一索引：同一商户同一支付方式只映射一个分组
            $table->unique(['merchant_id', 'pay_method'], 'udx_merchant_pay_method');
            // 按分组反查
            $table->index('payment_group_id', 'idx_payment_group_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('merchant_payment_group_mappings');
    }
};
