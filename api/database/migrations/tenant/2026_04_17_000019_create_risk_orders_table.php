<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('risk_orders', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('order_id')->comment('订单ID');
            $table->string('risk_level', 16)->default('low')->comment('风险等级: low/medium/high');
            $table->string('risk_reason', 500)->nullable()->comment('风险原因描述');
            $table->json('risk_factors')->nullable()->comment('风险因素JSON(IP/邮箱/地址等)');
            $table->tinyInteger('status')->default(1)->comment('状态: 1=待审核, 2=已审核通过, 3=已审核拒绝');
            $table->string('reviewer', 64)->nullable()->comment('审核人');
            $table->timestamp('reviewed_at')->nullable()->comment('审核时间');
            $table->timestamps();

            $table->index('order_id', 'idx_risk_orders_order_id');
            $table->index('risk_level', 'idx_risk_orders_risk_level');
            $table->index('status', 'idx_risk_orders_status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('risk_orders');
    }
};
