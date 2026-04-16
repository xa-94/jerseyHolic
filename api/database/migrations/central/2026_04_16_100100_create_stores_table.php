<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stores', function (Blueprint $table) {
            $table->engine = 'InnoDB';

            $table->string('id', 36)->primary();
            $table->unsignedBigInteger('merchant_id')->nullable()->index();
            $table->tinyInteger('status')->default(1)->index();
            $table->json('data')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stores');
    }
};
