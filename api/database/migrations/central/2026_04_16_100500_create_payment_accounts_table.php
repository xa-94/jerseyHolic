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
        Schema::create('payment_accounts', function (Blueprint $table) {
            $table->engine = 'InnoDB';

            $table->id();
            $table->unsignedInteger('group_id')->nullable()->comment('分组ID');
            $table->enum('type', ['paypal', 'stripe', 'antom', 'payssion', 'credit_card']);
            $table->string('account_identifier', 255)->comment('账号标识');
            $table->text('credentials')->comment('凭证(加密存储)');
            $table->enum('status', ['active', 'disabled', 'frozen', 'suspended'])->default('active');
            $table->enum('lifecycle_stage', ['new', 'growing', 'mature', 'aging'])->default('new');
            $table->decimal('health_score', 5, 2)->default(100.00);
            $table->decimal('daily_limit', 12, 2)->nullable();
            $table->decimal('monthly_limit', 14, 2)->nullable();
            $table->decimal('daily_used', 12, 2)->default(0);
            $table->decimal('monthly_used', 14, 2)->default(0);
            $table->integer('error_count_24h')->default(0);
            $table->timestamp('last_error_at')->nullable();
            $table->timestamp('activated_at')->nullable();
            $table->timestamps();

            $table->index('group_id');
            $table->index('type');
            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payment_accounts');
    }
};
