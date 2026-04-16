<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * 使用 central 数据库连接执行迁移
     */
    protected $connection = 'central';

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::connection('central')->create('merchant_audit_logs', function (Blueprint $table) {
            $table->engine = 'InnoDB';

            $table->id();

            // 关联商户（merchants 表，cascade delete）
            $table->unsignedBigInteger('merchant_id');
            $table->foreign('merchant_id')
                  ->references('id')
                  ->on('merchants')
                  ->cascadeOnDelete();

            // 关联操作管理员（nullable，不加外键约束——admins 表命名存历史原因）
            $table->unsignedBigInteger('admin_id')->nullable()->comment('操作管理员ID');

            // 操作类型：register, approve, reject, request_info, status_change, level_change
            $table->string('action', 50)->comment('操作类型');

            // 状态变更记录
            $table->string('from_status', 20)->nullable()->comment('变更前状态');
            $table->string('to_status', 20)->nullable()->comment('变更后状态');

            // 审核意见
            $table->text('comment')->nullable()->comment('审核意见/拒绝原因/补充要求');

            // 额外元数据（JSON）
            $table->json('metadata')->nullable()->comment('额外数据');

            $table->timestamps();

            // 联合索引：按商户 + 时间查询审核历史
            $table->index(['merchant_id', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::connection('central')->dropIfExists('merchant_audit_logs');
    }
};
