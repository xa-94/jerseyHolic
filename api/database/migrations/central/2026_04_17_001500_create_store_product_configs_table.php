<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('store_product_configs', function (Blueprint $table) {
            $table->id();
            $table->string('store_id', 36);
            $table->boolean('price_override_enabled')->default(false);
            $table->enum('price_override_strategy', ['multiplier', 'fixed', 'markup'])->nullable();
            $table->decimal('price_override_value', 10, 2)->nullable();
            $table->boolean('safe_name_override_enabled')->default(false);
            $table->string('custom_placeholder_image')->nullable();
            $table->string('display_currency', 3)->nullable();
            $table->boolean('auto_translate')->default(true);
            $table->string('default_language', 5)->default('en');
            $table->tinyInteger('status')->default(1);
            $table->timestamps();

            $table->unique('store_id');
            $table->foreign('store_id')->references('id')->on('stores')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('store_product_configs');
    }
};
