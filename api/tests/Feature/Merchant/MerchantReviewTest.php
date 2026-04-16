<?php

namespace Tests\Feature\Merchant;

use App\Models\Central\Admin;
use App\Models\Central\Merchant;
use App\Models\Central\MerchantAuditLog;
use App\Services\MerchantDatabaseService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TenancyTestCase;

/**
 * 商户审核流程集成测试
 *
 * 覆盖审核通过、拒绝、补充信息请求及审核日志记录功能。
 */
class MerchantReviewTest extends TenancyTestCase
{
    use RefreshDatabase;

    private Admin $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = Admin::create([
            'username' => 'reviewer',
            'email'    => 'reviewer@test.com',
            'password' => bcrypt('password'),
            'name'     => 'Reviewer Admin',
            'status'   => 1,
            'is_super' => 1,
        ]);
    }

    /* ----------------------------------------------------------------
     |  审核通过（approve）
     | ---------------------------------------------------------------- */

    /** 测试审核通过后商户状态变为 active（整型 1） */
    public function test_approve_sets_merchant_status_to_active(): void
    {
        $merchant = $this->createMerchant(['status' => 0]); // pending

        $response = $this->actingAs($this->admin, 'sanctum')
            ->postJson("/api/v1/admin/merchants/{$merchant->id}/review", [
                'action'  => 'approve',
                'comment' => '资质符合要求',
            ]);

        $response->assertStatus(200);

        $this->assertDatabaseHas('jh_merchants', [
            'id'     => $merchant->id,
            'status' => 1, // active
        ]);
    }

    /** 测试审核通过后自动创建商户专属数据库 jerseyholic_merchant_{id} */
    public function test_approve_creates_merchant_database(): void
    {
        $merchant = $this->createMerchant(['status' => 0]);
        $dbName   = 'jerseyholic_merchant_' . $merchant->id;

        // 先确保数据库不存在
        DB::connection('central')->statement("DROP DATABASE IF EXISTS `{$dbName}`");

        $this->actingAs($this->admin, 'sanctum')
            ->postJson("/api/v1/admin/merchants/{$merchant->id}/review", [
                'action' => 'approve',
            ]);

        // 检查数据库已创建
        $result = DB::connection('central')->select(
            "SELECT SCHEMA_NAME FROM information_schema.SCHEMATA WHERE SCHEMA_NAME = ?",
            [$dbName]
        );

        $this->assertNotEmpty($result, "商户专属数据库 {$dbName} 应当已被创建");

        // 清理：删除测试创建的数据库
        DB::connection('central')->statement("DROP DATABASE IF EXISTS `{$dbName}`");
    }

    /** 测试审核通过后商户库包含 3 张核心表（master_products, master_product_translations, sync_rules） */
    public function test_approve_creates_merchant_database_with_required_tables(): void
    {
        $merchant = $this->createMerchant(['status' => 0]);
        $dbName   = 'jerseyholic_merchant_' . $merchant->id;

        DB::connection('central')->statement("DROP DATABASE IF EXISTS `{$dbName}`");

        $this->actingAs($this->admin, 'sanctum')
            ->postJson("/api/v1/admin/merchants/{$merchant->id}/review", [
                'action' => 'approve',
            ]);

        $requiredTables = ['master_products', 'master_product_translations', 'sync_rules'];

        foreach ($requiredTables as $table) {
            $exists = DB::connection('central')->select(
                "SELECT TABLE_NAME FROM information_schema.TABLES
                 WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ?",
                [$dbName, $table]
            );
            $this->assertNotEmpty($exists, "商户库中应存在表 {$table}");
        }

        // 清理
        DB::connection('central')->statement("DROP DATABASE IF EXISTS `{$dbName}`");
    }

    /** 测试审核通过后 approved_at 字段被写入 */
    public function test_approve_sets_approved_at_timestamp(): void
    {
        $merchant = $this->createMerchant(['status' => 0]);

        $this->actingAs($this->admin, 'sanctum')
            ->postJson("/api/v1/admin/merchants/{$merchant->id}/review", [
                'action' => 'approve',
            ]);

        $dbName = 'jerseyholic_merchant_' . $merchant->id;

        $refreshed = Merchant::find($merchant->id);
        $this->assertNotNull($refreshed->approved_at, 'approved_at 应当在审核通过时被写入');

        // 清理
        DB::connection('central')->statement("DROP DATABASE IF EXISTS `{$dbName}`");
    }

    /* ----------------------------------------------------------------
     |  审核拒绝（reject）
     | ---------------------------------------------------------------- */

    /** 测试审核拒绝后商户状态变为 rejected，并记录拒绝原因 */
    public function test_reject_sets_merchant_status_to_rejected(): void
    {
        $merchant = $this->createMerchant(['status' => 0]); // pending

        $response = $this->actingAs($this->admin, 'sanctum')
            ->postJson("/api/v1/admin/merchants/{$merchant->id}/review", [
                'action'  => 'reject',
                'comment' => '证件不符合要求',
            ]);

        $response->assertStatus(200);

        $this->assertDatabaseHas('jh_merchants', [
            'id'     => $merchant->id,
            'status' => 2, // rejected
        ]);
    }

    /** 测试拒绝时审核日志包含拒绝原因 */
    public function test_reject_audit_log_contains_reason(): void
    {
        $merchant = $this->createMerchant(['status' => 0]);
        $reason   = '证件照片模糊，需重新提交';

        $this->actingAs($this->admin, 'sanctum')
            ->postJson("/api/v1/admin/merchants/{$merchant->id}/review", [
                'action'  => 'reject',
                'comment' => $reason,
            ]);

        $this->assertDatabaseHas('merchant_audit_logs', [
            'merchant_id' => $merchant->id,
            'action'      => 'reject',
            'to_status'   => 'rejected',
            'comment'     => $reason,
        ]);
    }

    /* ----------------------------------------------------------------
     |  要求补充信息（request_info）
     | ---------------------------------------------------------------- */

    /** 测试 request_info 操作后商户状态变为 info_required */
    public function test_request_info_sets_merchant_status_to_info_required(): void
    {
        $merchant = $this->createMerchant(['status' => 0]); // pending

        $response = $this->actingAs($this->admin, 'sanctum')
            ->postJson("/api/v1/admin/merchants/{$merchant->id}/review", [
                'action'  => 'request_info',
                'comment' => '请补充营业执照',
            ]);

        $response->assertStatus(200);

        $this->assertDatabaseHas('jh_merchants', [
            'id'     => $merchant->id,
            'status' => 3, // info_required
        ]);
    }

    /* ----------------------------------------------------------------
     |  审核日志记录
     | ---------------------------------------------------------------- */

    /** 测试审核操作完成后 merchant_audit_logs 表有对应记录 */
    public function test_audit_log_is_recorded_on_review(): void
    {
        $merchant = $this->createMerchant(['status' => 0]);

        $this->actingAs($this->admin, 'sanctum')
            ->postJson("/api/v1/admin/merchants/{$merchant->id}/review", [
                'action'  => 'approve',
                'comment' => '资质符合',
            ]);

        $this->assertDatabaseHas('merchant_audit_logs', [
            'merchant_id' => $merchant->id,
            'action'      => 'approve',
            'from_status' => 'pending',
            'to_status'   => 'active',
        ]);

        $dbName = 'jerseyholic_merchant_' . $merchant->id;
        DB::connection('central')->statement("DROP DATABASE IF EXISTS `{$dbName}`");
    }

    /** 测试非法 action 值返回 422 */
    public function test_review_with_invalid_action_returns_422(): void
    {
        $merchant = $this->createMerchant(['status' => 0]);

        $response = $this->actingAs($this->admin, 'sanctum')
            ->postJson("/api/v1/admin/merchants/{$merchant->id}/review", [
                'action' => 'invalid_action',
            ]);

        $response->assertStatus(422);
    }

    /** 测试已 active 商户不能再次审核 approve（状态路径不允许） */
    public function test_approve_already_active_merchant_returns_422(): void
    {
        $merchant = $this->createMerchant(['status' => 1]); // already active

        $response = $this->actingAs($this->admin, 'sanctum')
            ->postJson("/api/v1/admin/merchants/{$merchant->id}/review", [
                'action' => 'approve',
            ]);

        $response->assertStatus(422);
    }

    /** 测试多条审核日志可被正确查询（先 request_info 再 approve 产生两条日志） */
    public function test_multiple_audit_logs_are_recorded(): void
    {
        $merchant = $this->createMerchant(['status' => 0]);

        // 第一步：request_info
        $this->actingAs($this->admin, 'sanctum')
            ->postJson("/api/v1/admin/merchants/{$merchant->id}/review", [
                'action'  => 'request_info',
                'comment' => '补充材料',
            ]);

        // 更新状态回 pending，模拟商户补充后重新提交（或直接置 pending）
        $merchant->update(['status' => 0]);

        // 第二步：approve
        $this->actingAs($this->admin, 'sanctum')
            ->postJson("/api/v1/admin/merchants/{$merchant->id}/review", [
                'action' => 'approve',
            ]);

        $logCount = MerchantAuditLog::where('merchant_id', $merchant->id)->count();
        $this->assertGreaterThanOrEqual(2, $logCount);

        $dbName = 'jerseyholic_merchant_' . $merchant->id;
        DB::connection('central')->statement("DROP DATABASE IF EXISTS `{$dbName}`");
    }
}
