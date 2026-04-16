<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('settlement_refund_adjustments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('settlement_id')->nullable();
            $table->unsignedBigInteger('merchant_id');
            $table->unsignedBigInteger('order_id');
            $table->decimal('amount', 14, 2);
            $table->string('type', 32)->comment('deducted/deferred/pending');
            $table->boolean('applied')->default(false);
            $table->timestamps();

            $table->index('settlement_id');
            $table->index('merchant_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('settlement_refund_adjustments');
    }
};
