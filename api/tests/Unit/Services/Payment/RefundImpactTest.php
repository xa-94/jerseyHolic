<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Payment;

use App\Models\Central\Merchant;
use App\Models\Central\SettlementRecord;
use App\Models\Central\Store;
use App\Services\Payment\RefundImpactService;
use App\Services\Payment\SettlementService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class RefundImpactTest extends TestCase
{
    use RefreshDatabase;

    private RefundImpactService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new RefundImpactService();

        // 确保 settlement_refund_adjustments 表存在（在测试中可能需要迁移）
    }

    private function createMerchant(): Merchant
    {
        return Merchant::create([
            'merchant_name' => 'Test Merchant ' . uniqid(),
            'email'         => 'test_' . uniqid() . '@test.com',
            'password'      => bcrypt('password'),
            'contact_name'  => 'Test',
            'phone'         => '+1234567890',
            'level'         => 'starter',
            'status'        => 1,
        ]);
    }

    private function createStore(int $merchantId): Store
    {
        $storeId = (string) Str::uuid();
        return Store::withoutEvents(function () use ($storeId, $merchantId) {
            $s = new Store([
                'merchant_id'       => $merchantId,
                'store_name'        => 'Test Store ' . uniqid(),
                'store_code'        => 'test_' . uniqid(),
                'domain'            => uniqid() . '.test',
                'status'            => 1,
                'database_name'     => null,
                'database_password' => 'test',
            ]);
            $s->id = $storeId;
            $s->save();
            return $s;
        });
    }

    /* ----------------------------------------------------------------
     |  draft 状态结算单直接扣减
     | ---------------------------------------------------------------- */

    public function test_draft_settlement_gets_direct_deduction(): void
    {
        $merchant = $this->createMerchant();
        $store = $this->createStore($merchant->id);

        // 创建 draft 状态结算单
        $record = SettlementRecord::create([
            'merchant_id'       => $merchant->id,
            'settlement_no'     => 'STL-REF-001',
            'total_amount'      => '1000.00',
            'commission_amount' => '150.00',
            'net_amount'        => '850.00',
            'order_count'       => 10,
            'period_start'      => now()->startOfMonth()->toDateString(),
            'period_end'        => now()->endOfMonth()->toDateString(),
            'status'            => SettlementService::STATUS_DRAFT,
        ]);

        $result = $this->service->processRefund(1, '50.00', $store->id);

        $this->assertSame(RefundImpactService::IMPACT_DEDUCTED, $result['impact_type']);
        $this->assertSame($record->id, $result['settlement_id']);

        // 验证净额已扣减
        $record->refresh();
        $this->assertSame('800.00', $record->net_amount);
    }

    /* ----------------------------------------------------------------
     |  approved/paid 状态记录为下期抵扣
     | ---------------------------------------------------------------- */

    public function test_approved_settlement_defers_to_next_period(): void
    {
        $merchant = $this->createMerchant();
        $store = $this->createStore($merchant->id);

        SettlementRecord::create([
            'merchant_id'       => $merchant->id,
            'settlement_no'     => 'STL-REF-002',
            'total_amount'      => '2000.00',
            'commission_amount' => '300.00',
            'net_amount'        => '1700.00',
            'order_count'       => 20,
            'period_start'      => now()->startOfMonth()->toDateString(),
            'period_end'        => now()->endOfMonth()->toDateString(),
            'status'            => SettlementService::STATUS_APPROVED,
        ]);

        $result = $this->service->processRefund(2, '100.00', $store->id);

        $this->assertSame(RefundImpactService::IMPACT_DEFERRED, $result['impact_type']);
    }

    /* ----------------------------------------------------------------
     |  退款汇总统计正确
     | ---------------------------------------------------------------- */

    public function test_refund_summary_aggregation(): void
    {
        // 直接插入调整记录模拟
        DB::connection('central')->table('settlement_refund_adjustments')->insert([
            [
                'settlement_id' => 1, 'merchant_id' => 10, 'order_id' => 101,
                'amount' => '50.00', 'type' => RefundImpactService::IMPACT_DEDUCTED,
                'applied' => false, 'created_at' => now(), 'updated_at' => now(),
            ],
            [
                'settlement_id' => 1, 'merchant_id' => 10, 'order_id' => 102,
                'amount' => '30.00', 'type' => RefundImpactService::IMPACT_DEFERRED,
                'applied' => false, 'created_at' => now(), 'updated_at' => now(),
            ],
            [
                'settlement_id' => 2, 'merchant_id' => 10, 'order_id' => 103,
                'amount' => '20.00', 'type' => RefundImpactService::IMPACT_DEFERRED,
                'applied' => true, 'created_at' => now(), 'updated_at' => now(),
            ],
        ]);

        $summary = $this->service->getRefundSummary(10);

        $this->assertSame('100.00', $summary['total_refunded']);
        $this->assertSame('30.00', $summary['pending_deduction']);
        $this->assertSame('20.00', $summary['applied_deduction']);
    }

    /* ----------------------------------------------------------------
     |  无结算单时退款待处理
     | ---------------------------------------------------------------- */

    public function test_no_settlement_returns_pending(): void
    {
        $merchant = $this->createMerchant();
        $store = $this->createStore($merchant->id);

        $result = $this->service->processRefund(999, '25.00', $store->id);

        $this->assertSame(RefundImpactService::IMPACT_PENDING, $result['impact_type']);
        $this->assertNull($result['settlement_id']);
    }
}
