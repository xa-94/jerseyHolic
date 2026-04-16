<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_sync_logs', function (Blueprint $table) {
            $table->engine = 'InnoDB';

            $table->id();
            $table->foreignId('merchant_id')->constrained('merchants')->onDelete('cascade');
            $table->unsignedBigInteger('source_store_id')->nullable()->comment('源站点(null=主商品库)');
            $table->string('target_store_id', 36);
                        $table->foreign('target_store_id')->references('id')->on('stores')->onDelete('cascade');
            $table->enum('sync_type', ['full', 'incremental'])->default('incremental');
            $table->enum('trigger', ['manual', 'auto', 'scheduled'])->default('manual');
            $table->enum('status', ['pending', 'running', 'completed', 'failed', 'partial'])->default('pending');
            $table->integer('total_products')->default(0);
            $table->integer('synced_products')->default(0);
            $table->integer('failed_products')->default(0);
            $table->json('error_log')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index('merchant_id');
            $table->index('target_store_id');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_sync_logs');
    }
};
