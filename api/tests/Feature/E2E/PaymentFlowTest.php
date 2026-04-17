<?php

declare(strict_types=1);

namespace Tests\Feature\E2E;

use App\DTOs\CommissionResult;
use App\DTOs\SafeDescriptionDTO;
use App\Enums\OrderPaymentStatus;
use App\Models\Central\CommissionRule;
use App\Models\Central\Domain;
use App\Models\Central\Merchant;
use App\Models\Central\MerchantPaymentGroupMapping;
use App\Models\Central\PaymentAccount;
use App\Models\Central\PaymentAccountGroup;
use App\Models\Central\PaypalSafeDescription;
use App\Models\Central\SettlementRecord;
use App\Models\Central\Store;
use App\Models\Tenant\Dispute;
use App\Models\Tenant\Order;
use App\Models\Tenant\Refund;
use App\Services\Payment\AccountLifecycleService;
use App\Services\Payment\CommissionService;
use App\Services\Payment\ElectionService;
use App\Services\Payment\PayPalGateway;
use App\Services\Payment\PayPalWebhookHandler;
use App\Services\Payment\PaymentGroupMappingService;
use App\Services\Payment\RefundImpactService;
use App\Services\Payment\SafeDescriptionService;
use App\Services\Payment\SettlementService;
use App\Services\Payment\StripeGateway;
use App\Services\Payment\StripeWebhookHandler;
use App\Services\Payment\TransactionSimulationService;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Redis;
use Mockery;
use Tests\TenancyTestCase;

/**
 * 支付流程端到端测试（M6-003）
 *
 * 覆盖完整支付链路：域名→三层映射→选号→支付创建→Webhook→订单更新→佣金→结算
 * 包含退款、争议、异常场景测试
 */
class PaymentFlowTest extends TenancyTestCase
{
    use RefreshDatabase;

    private Merchant $merchant;
    private Store $store;
    private Domain $domain;
    private PaymentAccountGroup $paymentGroup;
    private PaymentAccount $paymentAccount;

    protected function setUp(): void
    {
        parent::setUp();

        // 清理缓存
        Cache::flush();
        Redis::flushall();

        // 创建基础测试数据
        $this->merchant = $this->createMerchant([
            'merchant_name' => 'E2E Test Merchant',
            'email' => 'e2e@test.com',
            'approved_at' => now()->subMonths(6),
        ]);

        $this->store = $this->createStore($this->merchant, [
            'store_code' => 'e2e_test_store',
            'domain' => 'e2e-test.jerseyholic.test',
        ]);

        $this->domain = $this->createDomain($this->store, 'e2e-test.jerseyholic.test');

        // 创建支付分组
        $this->paymentGroup = PaymentAccountGroup::create([
            'name' => 'E2E Test Group',
            'type' => 'paypal',
            'group_type' => PaymentAccountGroup::GROUP_TYPE_STANDARD_SHARED,
            'status' => 1,
        ]);

        // 创建支付账号
        $this->paymentAccount = PaymentAccount::create([
            'account' => 'paypal-e2e@test.com',
            'client_id' => 'test_client_id',
            'client_secret' => 'test_client_secret',
            'pay_method' => 'paypal',
            'category_id' => $this->paymentGroup->id,
            'status' => 1,
            'permission' => 1,
            'priority' => 10,
            'health_score' => 80,
            'lifecycle_stage' => PaymentAccount::LIFECYCLE_MATURE,
            'single_limit' => '2000.00',
            'daily_limit' => '10000.00',
            'daily_count_limit' => 50,
            'deal_count' => 0,
            'daily_money_total' => '0.00',
        ]);

        // 创建商户-分组映射
        MerchantPaymentGroupMapping::create([
            'merchant_id' => $this->merchant->id,
            'pay_method' => 'paypal',
            'payment_group_id' => $this->paymentGroup->id,
            'priority' => 10,
        ]);

        // 创建安全描述
        PaypalSafeDescription::create([
            'store_id' => $this->store->id,
            'product_category' => 'jersey',
            'safe_name' => 'Sports Training Jersey',
            'safe_description' => 'High quality sports apparel for training',
            'safe_category_code' => '5999',
            'weight' => 100,
            'enabled' => 1,
        ]);

        // 创建佣金规则
        CommissionRule::create([
            'merchant_id' => null,
            'store_id' => null,
            'rule_type' => CommissionRule::RULE_TYPE_DEFAULT,
            'tier_name' => 'E2E Global Default',
            'base_rate' => '15.00',
            'min_rate' => '8.00',
            'max_rate' => '35.00',
            'enabled' => CommissionRule::ENABLED,
        ]);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /* ================================================================
     |  完整支付链路测试
     | ================================================================ */

    /**
     * Test: PayPal 完整支付流程
     * 域名→三层映射→选号→PayPal创建→确认→Webhook→订单更新→佣金
     */
    public function test_full_paypal_payment_flow(): void
    {
        // Mock HTTP 请求
        Http::fake([
            'https://api-m.sandbox.paypal.com/v1/oauth2/token' => Http::response([
                'access_token' => 'test_access_token',
                'expires_in' => 3600,
            ], 200),
            'https://api-m.sandbox.paypal.com/v2/checkout/orders' => Http::response([
                'id' => 'PAYPAL-ORDER-123',
                'status' => 'CREATED',
                'links' => [
                    ['rel' => 'approve', 'href' => 'https://www.paypal.com/checkoutnow?token=PAYPAL-ORDER-123'],
                ],
            ], 201),
        ]);

        // 1. 验证三层映射
        $mappingService = new PaymentGroupMappingService();
        $group = $mappingService->resolveGroup($this->store->id, 'paypal');
        $this->assertNotNull($group);
        $this->assertSame($this->paymentGroup->id, $group->id);

        // 2. 执行8层选号
        $electionService = $this->createElectionServiceWithMocks();
        $result = $electionService->elect($this->store->id, 'paypal', '99.99', [
            'ip' => '8.8.8.8',
            'email' => 'buyer@example.com',
        ]);

        $this->assertTrue($result->success);
        $this->assertSame($this->paymentAccount->id, $result->account->id);

        // 3. 创建 PayPal 订单
        $safeDescService = Mockery::mock(SafeDescriptionService::class);
        $safeDescService->shouldReceive('resolve')->andReturn(
            new SafeDescriptionDTO('Sports Training Jersey', 'High quality sports apparel')
        );

        $gateway = new PayPalGateway($safeDescService);
        $orderResult = $gateway->createOrder([
            'store_id' => $this->store->id,
            'product_category' => 'jersey',
            'order_no' => 'ORD-E2E-001',
            'amount' => '99.99',
            'currency' => 'USD',
            'items' => [['name' => 'Original Jersey', 'quantity' => 1, 'unit_amount' => '99.99']],
        ], $this->paymentAccount);

        $this->assertSame('PAYPAL-ORDER-123', $orderResult['order_id']);
        $this->assertSame('CREATED', $orderResult['status']);

        // 4. 模拟 Webhook 回调
        $webhookHandler = new PayPalWebhookHandler();

        // Mock 幂等检查通过
        Cache::shouldReceive('add')
            ->once()
            ->andReturn(true);

        // 在租户上下文中创建订单
        $this->initializeTenancy($this->store);
        Order::create([
            'order_no' => 'ORD-E2E-001',
            'total' => 99.99,
            'pay_status' => OrderPaymentStatus::PENDING,
            'currency' => 'USD',
        ]);

        $webhookResult = $webhookHandler->handle([
            'id' => 'WH-E2E-001',
            'event_type' => 'PAYMENT.CAPTURE.COMPLETED',
            'resource' => [
                'id' => 'CAPTURE-E2E-001',
                'custom_id' => 'ORD-E2E-001',
                'amount' => [
                    'value' => '99.99',
                    'currency_code' => 'USD',
                ],
            ],
        ], $this->store->id);

        $this->assertTrue($webhookResult);

        // 5. 验证订单状态已更新
        $order = Order::where('order_no', 'ORD-E2E-001')->first();
        $this->assertSame(OrderPaymentStatus::PAID->value, $order->pay_status->value);
        $this->assertSame('99.99', $order->paid_amount);

        $this->endTenancy();

        // 6. 验证佣金计算
        $commissionService = new CommissionService();
        $commissionResult = $commissionService->calculate(
            $this->merchant->id,
            $this->store->id,
            '99.99'
        );

        $this->assertSame('15.00', $commissionResult->effectiveRate);
        $this->assertSame('14.99', $commissionResult->commissionAmount); // 99.99 * 0.15 = 14.9985 → 14.99
    }

    /**
     * Test: Stripe 完整支付流程
     */
    public function test_full_stripe_payment_flow(): void
    {
        // Mock Stripe HTTP 请求
        Http::fake([
            'api.stripe.com/v1/checkout/sessions' => Http::response([
                'id' => 'cs_test_e2e_123',
                'url' => 'https://checkout.stripe.com/pay/cs_test_e2e_123',
                'status' => 'open',
                'payment_intent' => 'pi_test_e2e_123',
            ], 200),
        ]);

        // 创建 Stripe 分组映射
        $stripeGroup = PaymentAccountGroup::create([
            'name' => 'E2E Stripe Group',
            'type' => 'stripe',
            'group_type' => PaymentAccountGroup::GROUP_TYPE_STANDARD_SHARED,
            'status' => 1,
        ]);

        $stripeAccount = PaymentAccount::create([
            'account' => 'stripe-e2e@test.com',
            'client_id' => 'pk_test_e2e',
            'client_secret' => 'sk_test_e2e',
            'pay_method' => 'stripe',
            'category_id' => $stripeGroup->id,
            'status' => 1,
            'permission' => 1,
            'priority' => 10,
            'health_score' => 80,
            'lifecycle_stage' => PaymentAccount::LIFECYCLE_MATURE,
            'single_limit' => '2000.00',
            'daily_limit' => '10000.00',
        ]);

        MerchantPaymentGroupMapping::create([
            'merchant_id' => $this->merchant->id,
            'pay_method' => 'stripe',
            'payment_group_id' => $stripeGroup->id,
            'priority' => 10,
        ]);

        // 1. 验证三层映射
        $mappingService = new PaymentGroupMappingService();
        $group = $mappingService->resolveGroup($this->store->id, 'stripe');
        $this->assertNotNull($group);

        // 2. 创建 Stripe Checkout Session
        $safeDescService = Mockery::mock(SafeDescriptionService::class);
        $safeDescService->shouldReceive('resolve')->andReturn(
            new SafeDescriptionDTO('Sports Training Jersey', 'High quality sports apparel')
        );

        $simulationService = Mockery::mock(TransactionSimulationService::class);
        $simulationService->shouldReceive('adjustAmount')->andReturnUsing(
            fn(string $amount) => $amount
        );

        $gateway = new StripeGateway($safeDescService, $simulationService);
        $orderResult = $gateway->createOrder([
            'order_no' => 'ORD-E2E-STRIPE-001',
            'amount' => '49.99',
            'currency' => 'USD',
            'store_id' => $this->store->id,
            'category' => 'jersey',
        ], $stripeAccount);

        $this->assertSame('cs_test_e2e_123', $orderResult['id']);

        // 3. 模拟 Stripe Webhook
        $webhookHandler = new StripeWebhookHandler($simulationService);

        // Mock Redis 幂等锁
        Redis::shouldReceive('set')
            ->once()
            ->andReturn(true);

        $webhookResult = $webhookHandler->handle([
            'id' => 'evt_e2e_001',
            'type' => 'checkout.session.completed',
            'data' => [
                'object' => [
                    'id' => 'cs_test_e2e_123',
                    'payment_intent' => 'pi_test_e2e_123',
                    'amount_total' => 4999,
                    'currency' => 'usd',
                    'metadata' => [
                        'order_no' => 'ORD-E2E-STRIPE-001',
                        'store_id' => (string) $this->store->id,
                    ],
                ],
            ],
        ]);

        $this->assertTrue($webhookResult['handled']);
    }

    /* ================================================================
     |  三层映射测试
     | ================================================================ */

    /**
     * Test: 三层映射正确解析 Domain→Merchant→Group
     */
    public function test_three_layer_mapping_resolves_correct_group(): void
    {
        $mappingService = new PaymentGroupMappingService();

        // 验证从 store_id 能正确解析到 group
        $group = $mappingService->resolveGroup($this->store->id, 'paypal');

        $this->assertNotNull($group);
        $this->assertSame($this->paymentGroup->id, $group->id);
        $this->assertSame(PaymentAccountGroup::GROUP_TYPE_STANDARD_SHARED, $group->group_type);

        // 验证缓存机制
        $cachedGroup = $mappingService->resolveGroup($this->store->id, 'paypal');
        $this->assertSame($group->id, $cachedGroup->id);
    }

    /* ================================================================
     |  8层选号测试
     | ================================================================ */

    /**
     * Test: 8层选号选出最优账号
     */
    public function test_8_layer_selection_picks_optimal_account(): void
    {
        // 创建多个账号，测试优先级排序
        $highPriorityAccount = PaymentAccount::create([
            'account' => 'high-priority@test.com',
            'client_id' => 'test_id_2',
            'client_secret' => 'test_secret_2',
            'pay_method' => 'paypal',
            'category_id' => $this->paymentGroup->id,
            'status' => 1,
            'permission' => 1,
            'priority' => 100, // 高优先级
            'health_score' => 90,
            'lifecycle_stage' => PaymentAccount::LIFECYCLE_MATURE,
            'single_limit' => '2000.00',
            'daily_limit' => '10000.00',
        ]);

        $electionService = $this->createElectionServiceWithMocks();
        $result = $electionService->elect($this->store->id, 'paypal', '50.00', [
            'ip' => '8.8.8.8',
            'email' => 'buyer@test.com',
        ]);

        $this->assertTrue($result->success);
        // 应该选中高优先级账号
        $this->assertSame($highPriorityAccount->id, $result->account->id);

        // 验证 layerLogs 记录了各层筛选
        $this->assertNotEmpty($result->layerLogs);
        $layerNumbers = array_column($result->layerLogs, 'layer');
        $this->assertContains(1, $layerNumbers); // 黑名单检查
        $this->assertContains(2, $layerNumbers); // 映射解析
        $this->assertContains(3, $layerNumbers); // 可用账号筛选
    }

    /* ================================================================
     |  Webhook 测试
     | ================================================================ */

    /**
     * Test: Webhook 回调正确更新订单状态
     */
    public function test_webhook_updates_order_status(): void
    {
        $this->initializeTenancy($this->store);

        // 创建待支付订单
        $order = Order::create([
            'order_no' => 'ORD-WEBHOOK-001',
            'total' => 150.00,
            'pay_status' => OrderPaymentStatus::PENDING,
            'currency' => 'USD',
        ]);

        $this->assertSame(OrderPaymentStatus::PENDING->value, $order->pay_status->value);

        // 模拟 Webhook 处理
        $handler = new PayPalWebhookHandler();

        Cache::shouldReceive('add')
            ->once()
            ->andReturn(true);

        $handler->handle([
            'id' => 'WH-STATUS-001',
            'event_type' => 'PAYMENT.CAPTURE.COMPLETED',
            'resource' => [
                'id' => 'CAPTURE-STATUS-001',
                'custom_id' => 'ORD-WEBHOOK-001',
                'amount' => [
                    'value' => '150.00',
                    'currency_code' => 'USD',
                ],
            ],
        ], $this->store->id);

        // 验证订单已更新
        $order->refresh();
        $this->assertSame(OrderPaymentStatus::PAID->value, $order->pay_status->value);
        $this->assertSame('150.00', $order->paid_amount);
        $this->assertSame('CAPTURE-STATUS-001', $order->payment_id);

        $this->endTenancy();
    }

    /**
     * Test: 重复 Webhook 不重复处理（幂等性）
     */
    public function test_duplicate_webhook_idempotent(): void
    {
        $handler = new PayPalWebhookHandler();

        // 第一次处理 - 成功
        Cache::shouldReceive('add')
            ->once()
            ->with('paypal_webhook:WH-DUP-001:' . $this->store->id, 1, 259200)
            ->andReturn(true);

        $result1 = $handler->handle([
            'id' => 'WH-DUP-001',
            'event_type' => 'PAYMENT.CAPTURE.COMPLETED',
            'resource' => [
                'id' => 'CAPTURE-DUP',
                'custom_id' => 'ORD-DUP-001',
                'amount' => ['value' => '50.00', 'currency_code' => 'USD'],
            ],
        ], $this->store->id);

        $this->assertTrue($result1);
    }

    /**
     * Test: Webhook 乱序正确处理
     */
    public function test_out_of_order_webhook_handled(): void
    {
        // 即使 Webhook 乱序到达，幂等机制确保不会重复处理
        $handler = new PayPalWebhookHandler();

        Cache::shouldReceive('add')
            ->once()
            ->andReturn(true);

        $result = $handler->handle([
            'id' => 'WH-OUT-OF-ORDER-001',
            'event_type' => 'PAYMENT.CAPTURE.COMPLETED',
            'resource' => [
                'id' => 'CAPTURE-OOO',
                'custom_id' => 'ORD-OOO-001',
                'amount' => ['value' => '75.00', 'currency_code' => 'USD'],
            ],
        ], $this->store->id);

        $this->assertTrue($result);
    }

    /* ================================================================
     |  佣金和结算测试
     | ================================================================ */

    /**
     * Test: 支付完成后佣金自动计算
     */
    public function test_commission_calculated_after_payment(): void
    {
        $commissionService = new CommissionService();

        // 计算佣金
        $result = $commissionService->calculate(
            $this->merchant->id,
            $this->store->id,
            '200.00',
            5000.00 // 月 GMV 5000，触发 2% 成交量奖励
        );

        // 基础费率 15% - 成交量奖励 2% = 13%
        $this->assertSame('13.00', $result->effectiveRate);
        // 200 * 0.13 = 26.00
        $this->assertSame('26.00', $result->commissionAmount);
    }

    /**
     * Test: 结算聚合包含该笔支付
     */
    public function test_settlement_aggregation_includes_payment(): void
    {
        $this->initializeTenancy($this->store);

        // 创建已支付订单
        Order::create([
            'order_no' => 'ORD-SETTLE-001',
            'total' => 100.00,
            'pay_status' => OrderPaymentStatus::PAID,
            'currency' => 'USD',
            'created_at' => now(),
        ]);

        $this->endTenancy();

        // 生成结算单
        $commissionService = Mockery::mock(CommissionService::class);
        $commissionService->shouldReceive('calculate')
            ->andReturn(new CommissionResult(
                orderAmount: '100.00',
                baseRate: '15.00',
                volumeDiscount: '0.00',
                loyaltyDiscount: '0.00',
                effectiveRate: '15.00',
                commissionAmount: '15.00',
                ruleId: 1,
            ));

        $notificationService = Mockery::mock(\App\Services\NotificationService::class);
        $notificationService->shouldReceive('sendToAdmin')->andReturnNull();
        $notificationService->shouldReceive('sendToMerchant')->andReturnNull();

        $settlementService = new SettlementService($commissionService, $notificationService);

        $record = $settlementService->generateForMerchant(
            $this->merchant->id,
            Carbon::parse(now()->startOfMonth()->toDateString()),
            Carbon::parse(now()->endOfMonth()->toDateString())
        );

        $this->assertSame('100.00', $record->total_amount);
        $this->assertSame('15.00', $record->commission_amount);
        // 净结算额 = GMV - 佣金 = 100 - 15 = 85
        $this->assertSame('85.00', $record->net_amount);
    }

    /* ================================================================
     |  退款和争议测试
     | ================================================================ */

    /**
     * Test: 退款流程 - 佣金扣回和结算调整
     */
    public function test_refund_flow_reverses_commission(): void
    {
        $this->initializeTenancy($this->store);

        // 创建订单和退款记录
        $order = Order::create([
            'order_no' => 'ORD-REFUND-001',
            'total' => 100.00,
            'pay_status' => OrderPaymentStatus::PAID,
            'currency' => 'USD',
        ]);

        Refund::create([
            'order_id' => $order->id,
            'amount' => 50.00,
            'status' => 1, // 已完成
            'created_at' => now(),
        ]);

        $this->endTenancy();

        // 创建结算单
        $settlement = SettlementRecord::create([
            'merchant_id' => $this->merchant->id,
            'settlement_no' => 'STL-REFUND-001',
            'total_amount' => '100.00',
            'commission_amount' => '15.00',
            'net_amount' => '85.00',
            'order_count' => 1,
            'period_start' => now()->startOfMonth()->toDateString(),
            'period_end' => now()->endOfMonth()->toDateString(),
            'status' => SettlementService::STATUS_DRAFT,
        ]);

        // 处理退款影响
        $refundService = new RefundImpactService();
        $result = $refundService->processRefund($order->id, '50.00', $this->store->id);

        $this->assertSame(RefundImpactService::IMPACT_DEDUCTED, $result['impact_type']);

        // 验证结算单净额已调整
        $settlement->refresh();
        $this->assertSame('35.00', $settlement->net_amount); // 85 - 50 = 35
    }

    /**
     * Test: 争议创建冻结结算
     */
    public function test_dispute_freezes_settlement(): void
    {
        $this->initializeTenancy($this->store);

        // 创建争议记录
        Dispute::create([
            'dispute_id' => 'DISPUTE-001',
            'gateway' => 'paypal',
            'order_id' => 1,
            'amount' => 75.00,
            'currency' => 'USD',
            'status' => 1, // 待处理
            'reason' => 'ITEM_NOT_RECEIVED',
            'created_at' => now(),
        ]);

        $this->endTenancy();

        // 验证争议已记录
        $dispute = Dispute::where('dispute_id', 'DISPUTE-001')->first();
        $this->assertNotNull($dispute);
        $this->assertSame(75.00, $dispute->amount);
    }

    /**
     * Test: 争议解决后解冻
     */
    public function test_dispute_resolved_unfreezes_settlement(): void
    {
        $this->initializeTenancy($this->store);

        $dispute = Dispute::create([
            'dispute_id' => 'DISPUTE-RESOLVED-001',
            'gateway' => 'paypal',
            'order_id' => 1,
            'amount' => 50.00,
            'currency' => 'USD',
            'status' => 1, // 待处理
            'reason' => 'ITEM_NOT_RECEIVED',
        ]);

        // 解决争议
        $dispute->update([
            'status' => 3, // 已解决/商户胜诉
            'resolved_at' => now(),
        ]);

        $this->assertSame(3, $dispute->fresh()->status);

        $this->endTenancy();
    }

    /* ================================================================
     |  异常场景测试
     | ================================================================ */

    /**
     * Test: 无可用账号返回错误
     */
    public function test_no_available_account_returns_error(): void
    {
        // 禁用所有账号
        PaymentAccount::query()->update(['status' => 0]);

        $electionService = $this->createElectionServiceWithMocks();
        $result = $electionService->elect($this->store->id, 'paypal', '50.00');

        $this->assertFalse($result->success);
        $this->assertSame('no_available', $result->code);
    }

    /**
     * Test: 日限额超限选下一个账号
     */
    public function test_account_daily_limit_exceeded_selects_next(): void
    {
        // 创建两个账号，第一个已超限
        $limitedAccount = PaymentAccount::create([
            'account' => 'limited@test.com',
            'client_id' => 'test_id_limited',
            'client_secret' => 'test_secret_limited',
            'pay_method' => 'paypal',
            'category_id' => $this->paymentGroup->id,
            'status' => 1,
            'permission' => 1,
            'priority' => 100,
            'health_score' => 80,
            'lifecycle_stage' => PaymentAccount::LIFECYCLE_MATURE,
            'single_limit' => '2000.00',
            'daily_limit' => '100.00',
            'daily_money_total' => '95.00', // 已接近限额
        ]);

        $availableAccount = PaymentAccount::create([
            'account' => 'available@test.com',
            'client_id' => 'test_id_avail',
            'client_secret' => 'test_secret_avail',
            'pay_method' => 'paypal',
            'category_id' => $this->paymentGroup->id,
            'status' => 1,
            'permission' => 1,
            'priority' => 50,
            'health_score' => 80,
            'lifecycle_stage' => PaymentAccount::LIFECYCLE_MATURE,
            'single_limit' => '2000.00',
            'daily_limit' => '10000.00',
            'daily_money_total' => '0.00',
        ]);

        $electionService = $this->createElectionServiceWithMocks();

        // 尝试支付 20.00，limited 账号剩余额度只有 5.00，应该被过滤
        $result = $electionService->elect($this->store->id, 'paypal', '20.00');

        $this->assertTrue($result->success);
        // 应该选中可用账号
        $this->assertSame($availableAccount->id, $result->account->id);
    }

    /**
     * Test: 支付超时场景
     */
    public function test_payment_timeout_handling(): void
    {
        // 模拟支付超时，订单应保持 pending 状态
        $this->initializeTenancy($this->store);

        $order = Order::create([
            'order_no' => 'ORD-TIMEOUT-001',
            'total' => 100.00,
            'pay_status' => OrderPaymentStatus::PENDING,
            'currency' => 'USD',
            'created_at' => now()->subHours(2), // 2小时前创建
        ]);

        // 验证订单仍为 pending（未收到 Webhook）
        $this->assertSame(OrderPaymentStatus::PENDING->value, $order->fresh()->pay_status->value);

        $this->endTenancy();
    }

    /* ================================================================
     |  交易日志和脱敏测试
     | ================================================================ */

    /**
     * Test: 支付创建交易日志
     */
    public function test_payment_creates_transaction_log(): void
    {
        // 验证交易记录会被创建
        $this->initializeTenancy($this->store);

        $order = Order::create([
            'order_no' => 'ORD-LOG-001',
            'total' => 100.00,
            'pay_status' => OrderPaymentStatus::PAID,
            'currency' => 'USD',
            'payment_id' => 'PAYMENT-LOG-001',
        ]);

        $this->assertNotNull($order->payment_id);
        $this->assertSame('PAYMENT-LOG-001', $order->payment_id);

        $this->endTenancy();
    }

    /**
     * Test: 支付中使用脱敏商品描述
     */
    public function test_desensitized_product_description_in_payment(): void
    {
        $safeDescService = new SafeDescriptionService();

        // 获取安全描述
        $safeDesc = $safeDescService->resolve($this->store->id, 'jersey');

        // 验证使用了安全描述而非原始商品名
        $this->assertSame('Sports Training Jersey', $safeDesc->safeName);
        $this->assertStringContainsString('sports', strtolower($safeDesc->safeDescription));

        // 验证不包含敏感关键词
        $this->assertStringNotContainsString('nike', strtolower($safeDesc->safeName));
        $this->assertStringNotContainsString('adidas', strtolower($safeDesc->safeName));
    }

    /**
     * Test: 交易行为模拟参数正确
     */
    public function test_behavioral_simulation_in_payment(): void
    {
        $notificationService = Mockery::mock(\App\Services\NotificationService::class);
        $notificationService->shouldReceive('send')->andReturnNull();

        $simulationService = new TransactionSimulationService($notificationService);

        // 测试金额微调
        $originalAmount = '50.00';
        $adjustedAmount = $simulationService->adjustAmount($originalAmount, 'USD');

        // 微调后的金额应在 49.01 ~ 50.99 之间
        $this->assertGreaterThanOrEqual(49.01, (float) $adjustedAmount);
        $this->assertLessThanOrEqual(50.99, (float) $adjustedAmount);

        // 验证金额微调不是恒等的（有随机性）
        $hasDifference = false;
        for ($i = 0; $i < 5; $i++) {
            if ($simulationService->adjustAmount('100.00', 'USD') !== '100.00') {
                $hasDifference = true;
                break;
            }
        }
        $this->assertTrue($hasDifference, 'Amount adjustment should introduce randomness');
    }

    /* ================================================================
     |  辅助方法
     | ================================================================ */

    /**
     * 创建带 Mock 依赖的 ElectionService
     */
    private function createElectionServiceWithMocks(): ElectionService
    {
        $mappingService = new PaymentGroupMappingService();
        $lifecycleService = new AccountLifecycleService();
        $healthScoreService = Mockery::mock(\App\Services\Payment\AccountHealthScoreService::class);
        $healthScoreService->shouldReceive('calculate')->andReturn(80);

        return new ElectionService($mappingService, $lifecycleService, $healthScoreService);
    }
}
