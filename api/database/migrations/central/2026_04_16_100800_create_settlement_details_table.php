<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('settlement_details', function (Blueprint $table) {
            $table->engine = 'InnoDB';

            $table->id();
            $table->foreignId('settlement_id')->constrained('merchant_settlements')->onDelete('cascade');
            $table->string('store_id', 36);
                        $table->foreign('store_id')->references('id')->on('stores')->onDelete('restrict');
            $table->integer('order_count')->default(0);
            $table->decimal('revenue', 12, 2)->default(0);
            $table->decimal('refunds', 12, 2)->default(0);
            $table->decimal('disputes', 12, 2)->default(0);
            $table->decimal('commission', 12, 2)->default(0);
            $table->decimal('net', 12, 2)->default(0);
            $table->timestamps();

            $table->index('settlement_id');
            $table->index('store_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('settlement_details');
    }
};
