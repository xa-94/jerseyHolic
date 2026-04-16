<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fund_flow_logs', function (Blueprint $table) {
            $table->engine = 'InnoDB';

            $table->id();
            $table->unsignedBigInteger('merchant_id')->nullable();
            $table->unsignedBigInteger('store_id')->nullable();
            $table->unsignedBigInteger('payment_account_id')->nullable();
            $table->enum('type', ['payment', 'refund', 'dispute', 'settlement', 'fee', 'adjustment']);
            $table->decimal('amount', 14, 2);
            $table->string('currency', 3)->default('USD');
            $table->string('reference_type', 50)->nullable()->comment('关联类型(order/refund/settlement)');
            $table->unsignedBigInteger('reference_id')->nullable()->comment('关联ID');
            $table->string('description', 500)->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('created_at')->nullable();

            $table->index('merchant_id');
            $table->index('store_id');
            $table->index('type');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fund_flow_logs');
    }
};
