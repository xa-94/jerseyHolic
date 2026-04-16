<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('settlement_records', function (Blueprint $table) {
            $table->engine = 'InnoDB';

            $table->id();
            $table->foreignId('merchant_id')->constrained('merchants')->onDelete('restrict');
            $table->string('settlement_no', 50)->unique()->comment('结算单号');
            $table->date('period_start')->comment('结算周期开始');
            $table->date('period_end')->comment('结算周期结束');
            $table->decimal('total_revenue', 14, 2)->comment('总收入');
            $table->decimal('total_refunds', 14, 2)->default(0)->comment('总退款');
            $table->decimal('total_disputes', 14, 2)->default(0)->comment('争议冻结');
            $table->decimal('platform_fee', 14, 2)->comment('平台佣金');
            $table->decimal('payment_processing_fee', 14, 2)->default(0)->comment('支付手续费');
            $table->decimal('net_amount', 14, 2)->comment('应结金额');
            $table->enum('status', ['draft', 'pending_review', 'approved', 'paid', 'disputed'])->default('draft');
            $table->unsignedBigInteger('approved_by')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index('merchant_id');
            $table->index('status');
            $table->index('period_start');
            $table->index('period_end');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('settlement_records');
    }
};
