<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 黑名单
        if (!Schema::hasTable('jh_blacklists')) {
        Schema::create('jh_blacklists', function (Blueprint $table) {
            $table->id();
            $table->string('type', 32)->comment('黑名单类型: ip/email/payment_account/phone/address');
            $table->string('value', 255)->comment('黑名单值');
            $table->string('reason', 255)->default('')->comment('加入原因');
            $table->string('operator', 64)->default('system')->comment('操作人');
            $table->tinyInteger('status')->default(1)->comment('状态: 0=已移除, 1=生效');
            $table->timestamp('expired_at')->nullable()->comment('过期时间，null=永久');
            $table->timestamps();

            $table->index(['type', 'value'], 'idx_blacklists_type_value');
            $table->index('status', 'idx_blacklists_status');
        });
        }

        // 风险订单标记
        if (!Schema::hasTable('jh_risk_orders')) {
        Schema::create('jh_risk_orders', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('order_id')->comment('订单ID');
            $table->tinyInteger('risk_level')->default(1)->comment('风险等级: 1=低, 2=中, 3=高');
            $table->string('risk_reason', 500)->default('')->comment('风险原因');
            $table->json('risk_factors')->nullable()->comment('风险因素JSON');
            $table->tinyInteger('status')->default(1)->comment('状态: 1=待审核, 2=已通过, 3=已拦截');
            $table->string('reviewer', 64)->default('')->comment('审核人');
            $table->timestamp('reviewed_at')->nullable()->comment('审核时间');
            $table->timestamps();

            $table->index('order_id', 'idx_risk_orders_order_id');
            $table->index('risk_level', 'idx_risk_orders_risk_level');
            $table->index('status', 'idx_risk_orders_status');
        });
        }

        // Sanctum 个人访问令牌表(Laravel Sanctum 需要) - 跳过，已由 Sanctum 自带迁移创建
        if (!Schema::hasTable('personal_access_tokens')) {
            Schema::create('personal_access_tokens', function (Blueprint $table) {
                $table->id();
                $table->morphs('tokenable');
                $table->string('name');
                $table->string('token', 64)->unique();
                $table->text('abilities')->nullable();
                $table->timestamp('last_used_at')->nullable();
                $table->timestamp('expires_at')->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('personal_access_tokens');
        Schema::dropIfExists('jh_risk_orders');
        Schema::dropIfExists('jh_blacklists');
    }
};
