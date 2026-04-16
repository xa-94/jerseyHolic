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
        Schema::create('merchants', function (Blueprint $table) {
            $table->engine = 'InnoDB';

            $table->id();
            $table->string('name', 100)->comment('商户名称');
            $table->string('code', 50)->unique()->comment('商户编码');
            $table->string('contact_name', 100)->comment('联系人');
            $table->string('contact_email', 150)->comment('联系邮箱');
            $table->string('contact_phone', 30)->nullable()->comment('联系电话');
            $table->enum('level', ['starter', 'standard', 'advanced', 'vip'])->default('starter')->comment('商户等级');
            $table->decimal('commission_rate', 5, 2)->default(20.00)->comment('佣金比例(%)');
            $table->enum('status', ['pending', 'active', 'suspended', 'banned'])->default('pending');
            $table->json('settings')->nullable()->comment('商户级别配置(品类/市场/语言/货币等)');
            $table->string('bank_name', 100)->nullable()->comment('银行名称');
            $table->string('bank_account', 100)->nullable()->comment('银行账号');
            $table->string('bank_holder', 100)->nullable()->comment('开户人');
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('suspended_at')->nullable();
            $table->timestamp('banned_at')->nullable();
            $table->string('ban_reason', 500)->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('status');
            $table->index('level');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('merchants');
    }
};
