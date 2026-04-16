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
        Schema::create('domains', function (Blueprint $table) {
            $table->engine = 'InnoDB';

            $table->id();
            $table->string('domain', 255)->unique();
            $table->foreignId('store_id')->constrained('stores')->onDelete('cascade');
            $table->boolean('is_primary')->default(false);
            $table->boolean('is_fallback')->default(false);
            $table->enum('certificate_status', ['pending', 'active', 'expired', 'failed'])->default('pending');
            $table->timestamps();

            $table->index('store_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('domains');
    }
};
