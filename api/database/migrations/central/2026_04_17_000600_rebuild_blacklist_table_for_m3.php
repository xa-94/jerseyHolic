<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 重建黑名单表 — M3 Schema 升级
 *
 * M1 版本黑名单表结构较简单（type/value/reason/source/expires_at），
 * M3 升级为支持平台级 + 商户级双维度风控，新增 scope/merchant_id/dimension 等字段。
 *
 * 由于结构差异较大，采用 drop-recreate 方式重建。
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('blacklist');

        Schema::create('blacklist', function (Blueprint $table) {
            $table->engine = 'InnoDB';

            $table->id();
            $table->string('scope', 16)->comment('作用范围：platform=全平台, merchant=仅商户级');
            $table->unsignedBigInteger('merchant_id')->nullable()->comment('商户 ID，scope=platform 时为 null');
            $table->string('dimension', 32)->comment('维度：ip/email/device/payment_account');
            $table->string('value', 255)->comment('黑名单值（IP/邮箱/设备指纹/支付账号标识）');
            $table->string('reason', 500)->default('')->comment('加入原因');
            $table->timestamp('expires_at')->nullable()->comment('过期时间，null=永久生效');
            $table->timestamps();

            // 按范围+维度查询
            $table->index(['scope', 'dimension'], 'idx_scope_dimension');
            // 风控实时拦截查询（核心查询路径）
            $table->index(['dimension', 'value'], 'idx_dimension_value');
            // 按商户查询
            $table->index('merchant_id', 'idx_merchant_id');
            // 过期清理
            $table->index('expires_at', 'idx_expires_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('blacklist');

        // 回滚时恢复 M1 版本结构
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
};
