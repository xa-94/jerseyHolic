<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('blacklist', function (Blueprint $table) {
            $table->engine = 'InnoDB';

            $table->id();
            $table->enum('type', ['email', 'ip', 'device', 'phone', 'payment_method']);
            $table->string('value', 255);
            $table->string('reason', 500)->nullable();
            $table->enum('source', ['manual', 'auto'])->default('manual');
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();

            $table->unique(['type', 'value'], 'blacklist_type_value_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('blacklist');
    }
};
