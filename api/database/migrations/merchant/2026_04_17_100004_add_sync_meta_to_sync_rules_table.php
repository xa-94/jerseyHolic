<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 补充 sync_rules 表缺失的 last_synced_at 和 sync_interval_hours 列。
 *
 * ProductSyncService::incrementalSync() 和 DailyProductSyncVerificationJob 都依赖这两个字段。
 */
return new class extends Migration
{
    protected $connection = 'merchant';

    public function up(): void
    {
        Schema::connection($this->connection)->table('sync_rules', function (Blueprint $table) {
            $table->timestamp('last_synced_at')->nullable()->after('status');
            $table->integer('sync_interval_hours')->default(24)->after('last_synced_at');
        });
    }

    public function down(): void
    {
        Schema::connection($this->connection)->table('sync_rules', function (Blueprint $table) {
            $table->dropColumn(['last_synced_at', 'sync_interval_hours']);
        });
    }
};
