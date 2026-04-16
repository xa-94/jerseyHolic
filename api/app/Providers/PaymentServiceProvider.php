<?php

declare(strict_types=1);

namespace App\Providers;

use App\Jobs\GenerateSettlementJob;
use App\Services\Payment\PaymentGatewayInterface;
use App\Services\Payment\PayPalGateway;
use App\Services\Payment\StripeGateway;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\ServiceProvider;

/**
 * 支付服务 Provider
 *
 * 注册支付网关接口绑定 & 定时任务。
 */
class PaymentServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        // 根据参数或默认配置绑定对应的网关实现
        $this->app->bind(PaymentGatewayInterface::class, function ($app, $params) {
            $gateway = $params['gateway'] ?? config('payment.default_gateway');

            return match ($gateway) {
                'paypal' => $app->make(PayPalGateway::class),
                'stripe' => $app->make(StripeGateway::class),
                default  => throw new \InvalidArgumentException("Unsupported payment gateway: {$gateway}"),
            };
        });
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        // 注册定时任务（仅 CLI 环境）
        if ($this->app->runningInConsole()) {
            $this->callAfterResolving(Schedule::class, function (Schedule $schedule) {
                $schedule->job(new GenerateSettlementJob(), 'report')
                    ->monthlyOn(1, '02:00')
                    ->withoutOverlapping();
            });
        }
    }
}
