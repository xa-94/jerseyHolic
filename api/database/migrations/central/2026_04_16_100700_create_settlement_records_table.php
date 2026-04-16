<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('merchant_settlements', function (Blueprint $table) {
            $table->engine = 'InnoDB';

            $table->id();
            $table->unsignedBigInteger('merchant_id');
            $table->string('settlement_no', 50)->unique()->comment('结算单号');
            $table->decimal('total_amount', 14, 2)->default(0)->comment('总金额');
            $table->decimal('commission_amount', 14, 2)->default(0)->comment('佣金金额');
            $table->decimal('net_amount', 14, 2)->default(0)->comment('净结算额');
            $table->unsignedInteger('order_count')->default(0)->comment('订单数');
            $table->date('period_start')->comment('结算周期开始');
            $table->date('period_end')->comment('结算周期结束');
            $table->tinyInteger('status')->default(0)->comment('状态: 0=草稿, 1=待审核, 2=已打款, 3=已取消, 4=已批准, 5=已拒绝');
            $table->unsignedBigInteger('reviewed_by')->nullable()->comment('审核人');
            $table->timestamp('reviewed_at')->nullable()->comment('审核时间');
            $table->string('transaction_ref', 255)->nullable()->comment('打款流水号');
            $table->text('remark')->nullable()->comment('备注');
            $table->timestamp('settled_at')->nullable()->comment('结算时间');
            $table->timestamps();

            $table->foreign('merchant_id')->references('id')->on('merchants')->onDelete('restrict');
            $table->index('merchant_id');
            $table->index('status');
            $table->index('period_start');
            $table->index('period_end');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('merchant_settlements');
    }
};
