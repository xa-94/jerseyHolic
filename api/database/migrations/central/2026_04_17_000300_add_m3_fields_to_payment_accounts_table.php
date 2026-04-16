<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 为 payment_accounts 添加 M3 温养/统计/限额字段
 *
 * 这些字段用于支付账号生命周期管理、健康度评估和风控限额。
 * 部分概念在 M3 设计中映射到现有字段（如 permission→lifecycle），
 * 此处新增独立字段以支持更精细的温养策略。
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payment_accounts', function (Blueprint $table) {
            // 仅添加 create 表中尚不存在的字段
            if (! Schema::hasColumn('payment_accounts', 'single_limit')) {
                $table->decimal('single_limit', 14, 2)
                      ->default(50.00)
                      ->after('monthly_limit')
                      ->comment('单笔限额(USD)');
            }
            if (! Schema::hasColumn('payment_accounts', 'daily_count_limit')) {
                $table->unsignedInteger('daily_count_limit')
                      ->default(3)
                      ->after('single_limit')
                      ->comment('日最大笔数');
            }
            if (! Schema::hasColumn('payment_accounts', 'total_success_count')) {
                $table->unsignedInteger('total_success_count')
                      ->default(0)
                      ->comment('累计成功笔数');
            }
            if (! Schema::hasColumn('payment_accounts', 'total_fail_count')) {
                $table->unsignedInteger('total_fail_count')
                      ->default(0)
                      ->comment('累计失败笔数');
            }
            if (! Schema::hasColumn('payment_accounts', 'total_refund_count')) {
                $table->unsignedInteger('total_refund_count')
                      ->default(0)
                      ->comment('累计退款笔数');
            }
            if (! Schema::hasColumn('payment_accounts', 'total_dispute_count')) {
                $table->unsignedInteger('total_dispute_count')
                      ->default(0)
                      ->comment('累计争议笔数');
            }
            if (! Schema::hasColumn('payment_accounts', 'last_used_at')) {
                $table->timestamp('last_used_at')
                      ->nullable()
                      ->comment('最后使用时间');
            }
            if (! Schema::hasColumn('payment_accounts', 'cooling_until')) {
                $table->timestamp('cooling_until')
                      ->nullable()
                      ->comment('冷却截止时间');
            }
        });
    }

    public function down(): void
    {
        Schema::table('payment_accounts', function (Blueprint $table) {
            $table->dropIndex('idx_pa_lifecycle_stage');
            $table->dropIndex('idx_pa_health_score');

            $table->dropColumn([
                'lifecycle_stage',
                'health_score',
                'daily_limit',
                'monthly_limit',
                'single_limit',
                'daily_count_limit',
                'total_success_count',
                'total_fail_count',
                'total_refund_count',
                'total_dispute_count',
                'last_used_at',
                'cooling_until',
            ]);
        });
    }
};
