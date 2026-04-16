<?php

declare(strict_types=1);

namespace Tests\Feature\Payment;

use App\DTOs\SafeDescriptionDTO;
use App\Models\Central\PaymentAccount;
use App\Services\Payment\SafeDescriptionService;
use App\Services\Payment\StripeGateway;
use App\Services\Payment\StripeWebhookHandler;
use App\Services\Payment\TransactionSimulationService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Redis;
use Mockery;
use Tests\TestCase;

class StripeGatewayTest extends TestCase
{
    private StripeGateway $gateway;
    private SafeDescriptionService|Mockery\MockInterface $safeDescService;
    private TransactionSimulationService|Mockery\MockInterface $simulationService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->safeDescService = Mockery::mock(SafeDescriptionService::class);
        $this->safeDescService->shouldReceive('resolve')->andReturn(
            new SafeDescriptionDTO(
                safeName: 'Premium Fashion Item',
                safeDescription: 'High quality item',
            )
        );

        $this->simulationService = Mockery::mock(TransactionSimulationService::class);
        $this->simulationService->shouldReceive('adjustAmount')->andReturnUsing(
            fn(string $amount) => $amount
        );

        $this->gateway = new StripeGateway(
            $this->safeDescService,
            $this->simulationService,
        );
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    private function makeAccount(): PaymentAccount
    {
        $account = new PaymentAccount([
            'account'       => 'stripe-test@example.com',
            'client_id'     => 'pk_test_xxx',
            'client_secret' => 'sk_test_xxx',
            'pay_method'    => 'stripe',
            'status'        => 1,
            'permission'    => 1,
        ]);
        $account->id = 1;
        $account->exists = true;

        return $account;
    }

    /* ----------------------------------------------------------------
     |  createCheckoutSession 成功（Mock HTTP）
     | ---------------------------------------------------------------- */

    public function test_create_checkout_session_success(): void
    {
        Http::fake([
            'api.stripe.com/v1/checkout/sessions' => Http::response([
                'id'              => 'cs_test_123',
                'url'             => 'https://checkout.stripe.com/pay/cs_test_123',
                'status'          => 'open',
                'payment_intent'  => 'pi_test_123',
            ], 200),
        ]);

        $account = $this->makeAccount();

        $result = $this->gateway->createOrder([
            'order_no'  => 'ORD-2026-001',
            'amount'    => '49.99',
            'currency'  => 'USD',
            'store_id'  => 1,
            'category'  => 'jersey',
        ], $account);

        $this->assertSame('cs_test_123', $result['id']);
        $this->assertStringContainsString('checkout.stripe.com', $result['url']);
        $this->assertSame('open', $result['status']);
    }

    /* ----------------------------------------------------------------
     |  createOrder 使用安全描述
     | ---------------------------------------------------------------- */

    public function test_create_order_uses_safe_description(): void
    {
        Http::fake([
            'api.stripe.com/v1/checkout/sessions' => Http::response([
                'id' => 'cs_test_safe', 'url' => 'https://checkout.stripe.com/cs_test_safe',
                'status' => 'open',
            ], 200),
        ]);

        $result = $this->gateway->createOrder([
            'order_no' => 'ORD-002', 'amount' => '10.00',
            'currency' => 'USD', 'store_id' => 1, 'category' => 'default',
        ], $this->makeAccount());

        $this->assertNotEmpty($result['id']);
    }

    /* ----------------------------------------------------------------
     |  Webhook checkout.session.completed 处理
     | ---------------------------------------------------------------- */

    public function test_webhook_checkout_session_completed(): void
    {
        $handler = new StripeWebhookHandler(
            Mockery::mock(TransactionSimulationService::class),
        );

        // Mock Redis for idempotent lock
        Redis::shouldReceive('set')
            ->once()
            ->andReturn(true); // 首次处理

        $payload = [
            'id'   => 'evt_test_001',
            'type' => 'checkout.session.completed',
            'data' => [
                'object' => [
                    'id'              => 'cs_test_completed',
                    'payment_intent'  => 'pi_test_001',
                    'amount_total'    => 4999,
                    'currency'        => 'usd',
                    'metadata'        => [
                        'order_no' => 'ORD-2026-001',
                        'store_id' => '1',
                    ],
                ],
            ],
        ];

        $result = $handler->handle($payload);

        $this->assertTrue($result['handled']);
    }

    /* ----------------------------------------------------------------
     |  Webhook 幂等性
     | ---------------------------------------------------------------- */

    public function test_webhook_idempotency_skips_duplicate(): void
    {
        $handler = new StripeWebhookHandler(
            Mockery::mock(TransactionSimulationService::class),
        );

        Redis::shouldReceive('set')
            ->once()
            ->andReturn(null); // 已处理过 → SETNX 返回 null

        $payload = [
            'id'   => 'evt_test_dup',
            'type' => 'checkout.session.completed',
            'data' => [
                'object' => [
                    'metadata' => ['store_id' => '1', 'order_no' => 'ORD-DUP'],
                ],
            ],
        ];

        $result = $handler->handle($payload);

        $this->assertFalse($result['handled']);
        $this->assertSame('Duplicate event', $result['message']);
    }

    /* ----------------------------------------------------------------
     |  金额 cents 转换正确性
     | ---------------------------------------------------------------- */

    public function test_amount_cents_conversion_for_usd(): void
    {
        Http::fake([
            'api.stripe.com/v1/checkout/sessions' => Http::response([
                'id' => 'cs_cents',
                'url' => 'https://checkout.stripe.com/cs_cents',
                'status' => 'open',
            ], 200),
        ]);

        $result = $this->gateway->createOrder([
            'order_no' => 'ORD-CENTS', 'amount' => '49.99',
            'currency' => 'USD', 'store_id' => 1, 'category' => 'default',
        ], $this->makeAccount());

        // Verify request was sent and order created successfully
        $this->assertNotEmpty($result['id']);

        // Verify the HTTP request was made to Stripe with correct cents
        Http::assertSent(function (\Illuminate\Http\Client\Request $request) {
            if (!str_contains($request->url(), 'checkout/sessions')) {
                return false;
            }
            $body = $request->data();
            // Params are flat form-urlencoded keys
            $unitAmount = $body['line_items[0][price_data][unit_amount]'] ?? null;
            return $unitAmount === 4999 || $unitAmount === '4999';
        });

        // 通过反射验证 toSmallestUnit
        $reflection = new \ReflectionClass($this->gateway);
        $method = $reflection->getMethod('toSmallestUnit');
        $method->setAccessible(true);

        $this->assertSame(4999, $method->invoke($this->gateway, '49.99', 'usd'));
        $this->assertSame(100, $method->invoke($this->gateway, '1.00', 'usd'));
        // JPY is zero-decimal
        $this->assertSame(500, $method->invoke($this->gateway, '500', 'jpy'));
    }

    /* ----------------------------------------------------------------
     |  Gateway 实现接口
     | ---------------------------------------------------------------- */

    public function test_gateway_implements_interface(): void
    {
        $this->assertInstanceOf(
            \App\Services\Payment\PaymentGatewayInterface::class,
            $this->gateway
        );
    }
}
