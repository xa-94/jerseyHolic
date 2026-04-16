<?php

declare(strict_types=1);

namespace App\Services\Payment;

use App\Models\Central\PaymentAccount;
use App\Services\NotificationService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;

/**
 * 交易行为模拟保护服务（M3-011）
 *
 * 四大防护维度，用于降低支付平台风控触发概率：
 *
 *  1. 金额微调 — 在真实金额基础上随机浮动 ±$0.01~$0.99
 *  2. 时间限频 — 同一账号每日最大交易笔数限制
 *  3. IP 地理监控 — 检查买家 IP 与收货地址国家一致性
 *  4. 退款率预警 — 当账号退款率/争议率超阈值时告警
 *
 * 本服务被 PayPal 和 Stripe 两个 Gateway 共同调用。
 */
class TransactionSimulationService
{
    /** Redis key 前缀：支付频率限制 */
    private const FREQ_KEY_PREFIX = 'payment_freq';

    /** 默认每日最大交易笔数 */
    private const DEFAULT_DAILY_LIMIT = 50;

    /** 退款率预警阈值（warning） */
    private const REFUND_RATE_WARNING = 0.01;

    /** 退款率预警阈值（critical） */
    private const REFUND_RATE_CRITICAL = 0.02;

    /** 争议率预警阈值（warning） */
    private const DISPUTE_RATE_WARNING = 0.005;

    /** 争议率预警阈值（critical） */
    private const DISPUTE_RATE_CRITICAL = 0.01;

    public function __construct(
        private readonly NotificationService $notificationService,
    ) {}

    /* ----------------------------------------------------------------
     |  1. 金额微调
     | ---------------------------------------------------------------- */

    /**
     * 在真实金额基础上随机浮动 ±$0.01~$0.99
     *
     * 目的：防止相同金额模式被风控系统识别。
     * 全程使用 bcmath 保证精度。
     *
     * @param  string $amount   原始金额（如 "49.99"）
     * @param  string $currency 货币代码（如 "USD"）
     * @return string 微调后金额（如 "50.37"）
     */
    public function adjustAmount(string $amount, string $currency): string
    {
        // 生成 0.01 ~ 0.99 的随机浮动值
        $cents = random_int(1, 99);
        $fluctuation = bcdiv((string) $cents, '100', 2);

        // 随机决定加或减
        $direction = random_int(0, 1) === 1 ? '1' : '-1';
        $adjustment = bcmul($fluctuation, $direction, 2);

        $adjusted = bcadd($amount, $adjustment, 2);

        // 确保金额不低于 $0.50（Stripe 最低限制）
        if (bccomp($adjusted, '0.50', 2) < 0) {
            $adjusted = bcadd($amount, $fluctuation, 2);
        }

        Log::debug('[TransactionSimulation] Amount adjusted.', [
            'original'  => $amount,
            'adjusted'  => $adjusted,
            'delta'     => $adjustment,
            'currency'  => $currency,
        ]);

        return $adjusted;
    }

    /* ----------------------------------------------------------------
     |  2. 时间限频
     | ---------------------------------------------------------------- */

    /**
     * 检查账号今日交易频率是否已达上限
     *
     * 使用 Redis INCR + EXPIRE 实现原子计数，TTL 为当日剩余秒数。
     *
     * @param  int $accountId 支付账号 ID
     * @return bool true = 未超限（可继续交易），false = 已超限
     */
    public function checkFrequencyLimit(int $accountId): bool
    {
        $date = date('Ymd');
        $key  = self::FREQ_KEY_PREFIX . ":{$accountId}:{$date}";

        // 获取账号配置的每日上限
        $dailyLimit = $this->getDailyLimit($accountId);

        /** @var int $current */
        $current = (int) Redis::get($key);

        if ($current >= $dailyLimit) {
            Log::warning('[TransactionSimulation] Frequency limit reached.', [
                'account_id'  => $accountId,
                'current'     => $current,
                'daily_limit' => $dailyLimit,
            ]);
            return false;
        }

        return true;
    }

    /**
     * 递增账号今日交易计数
     *
     * 应在交易成功创建后调用。
     *
     * @param  int $accountId 支付账号 ID
     * @return int 递增后的当前计数
     */
    public function incrementFrequencyCount(int $accountId): int
    {
        $date = date('Ymd');
        $key  = self::FREQ_KEY_PREFIX . ":{$accountId}:{$date}";

        $count = (int) Redis::incr($key);

        // 首次写入时设置过期时间（当日剩余秒数）
        if ($count === 1) {
            $ttl = strtotime('tomorrow') - time();
            Redis::expire($key, $ttl);
        }

        return $count;
    }

    /* ----------------------------------------------------------------
     |  3. IP 地理监控
     | ---------------------------------------------------------------- */

    /**
     * 检查买家 IP 所在国家与收货地址国家的一致性
     *
     * 不一致时仅记录 warning 日志，不阻断交易。
     *
     * @param  string $buyerIp         买家 IP 地址
     * @param  string $shippingCountry 收货地址国家代码（ISO 3166-1 alpha-2）
     * @return array{consistent: bool, ip_country: string, shipping_country: string}
     */
    public function checkGeoConsistency(string $buyerIp, string $shippingCountry): array
    {
        $ipCountry = $this->resolveIpCountry($buyerIp);

        $consistent = $ipCountry === 'unknown'
            || strtoupper($ipCountry) === strtoupper($shippingCountry);

        $result = [
            'consistent'       => $consistent,
            'ip_country'       => $ipCountry,
            'shipping_country' => $shippingCountry,
        ];

        if (!$consistent) {
            Log::warning('[TransactionSimulation] Geo inconsistency detected.', [
                'buyer_ip'         => $buyerIp,
                'ip_country'       => $ipCountry,
                'shipping_country' => $shippingCountry,
            ]);
        }

        return $result;
    }

    /* ----------------------------------------------------------------
     |  4. 退款率预警
     | ---------------------------------------------------------------- */

    /**
     * 检查账号退款率/争议率是否触发预警
     *
     * 退款率 > 1%：warning 通知
     * 退款率 > 2%：critical 通知 + 调用 NotificationService
     * 争议率 > 0.5%：warning 通知
     * 争议率 > 1%：critical 通知
     *
     * @param  int $accountId 支付账号 ID
     * @return array|null null = 正常，array = 预警详情
     */
    public function checkRefundRateAlert(int $accountId): ?array
    {
        $account = PaymentAccount::find($accountId);

        if ($account === null) {
            return null;
        }

        $totalTransactions = $account->total_success_count + $account->total_fail_count;

        // 交易量不足时不计算（避免小样本误报）
        if ($totalTransactions < 10) {
            return null;
        }

        $refundRate  = $account->total_refund_count / $totalTransactions;
        $disputeRate = $account->total_dispute_count / $totalTransactions;

        $alerts = [];

        // 退款率检查
        if ($refundRate > self::REFUND_RATE_CRITICAL) {
            $alerts[] = [
                'type'      => 'refund_rate',
                'level'     => 'critical',
                'rate'      => round($refundRate * 100, 2),
                'threshold' => self::REFUND_RATE_CRITICAL * 100,
            ];
        } elseif ($refundRate > self::REFUND_RATE_WARNING) {
            $alerts[] = [
                'type'      => 'refund_rate',
                'level'     => 'warning',
                'rate'      => round($refundRate * 100, 2),
                'threshold' => self::REFUND_RATE_WARNING * 100,
            ];
        }

        // 争议率检查
        if ($disputeRate > self::DISPUTE_RATE_CRITICAL) {
            $alerts[] = [
                'type'      => 'dispute_rate',
                'level'     => 'critical',
                'rate'      => round($disputeRate * 100, 2),
                'threshold' => self::DISPUTE_RATE_CRITICAL * 100,
            ];
        } elseif ($disputeRate > self::DISPUTE_RATE_WARNING) {
            $alerts[] = [
                'type'      => 'dispute_rate',
                'level'     => 'warning',
                'rate'      => round($disputeRate * 100, 2),
                'threshold' => self::DISPUTE_RATE_WARNING * 100,
            ];
        }

        if (empty($alerts)) {
            return null;
        }

        // 发送通知（critical 级别调用 NotificationService）
        $this->dispatchRefundAlertNotifications($account, $alerts);

        Log::warning('[TransactionSimulation] Refund/Dispute rate alert triggered.', [
            'account_id' => $accountId,
            'account'    => $account->account,
            'alerts'     => $alerts,
        ]);

        return [
            'account_id' => $accountId,
            'account'    => $account->account,
            'alerts'     => $alerts,
        ];
    }

    /* ----------------------------------------------------------------
     |  私有方法
     | ---------------------------------------------------------------- */

    /**
     * 获取账号每日交易上限
     */
    private function getDailyLimit(int $accountId): int
    {
        $account = PaymentAccount::find($accountId);

        if ($account !== null && $account->daily_count_limit > 0) {
            return $account->daily_count_limit;
        }

        return (int) config('payment.simulation.daily_frequency_limit', self::DEFAULT_DAILY_LIMIT);
    }

    /**
     * 解析 IP 地址所在国家（ISO 3166-1 alpha-2）
     *
     * 优先使用 geoip2/geoip2 包，不可用时降级为简单 IP 段映射。
     */
    private function resolveIpCountry(string $ip): string
    {
        // 尝试使用 GeoIP2
        if (class_exists(\GeoIp2\Database\Reader::class)) {
            try {
                $dbPath = config('services.geoip.database_path', storage_path('app/geoip/GeoLite2-Country.mmdb'));

                if (file_exists($dbPath)) {
                    $reader = new \GeoIp2\Database\Reader($dbPath);
                    $record = $reader->country($ip);
                    return $record->country->isoCode ?? 'unknown';
                }
            } catch (\Exception $e) {
                Log::debug('[TransactionSimulation] GeoIP2 lookup failed.', [
                    'ip'    => $ip,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        // 降级：简单 IP 段映射（仅覆盖常见段，生产环境应使用 GeoIP2）
        return $this->fallbackIpCountry($ip);
    }

    /**
     * 简单 IP 段国家映射（降级方案）
     *
     * 仅作为 GeoIP2 不可用时的兜底，覆盖有限。
     */
    private function fallbackIpCountry(string $ip): string
    {
        $parts = explode('.', $ip);

        if (count($parts) !== 4) {
            return 'unknown';
        }

        $firstOctet = (int) $parts[0];

        // 极简映射（生产环境不应依赖此逻辑）
        return match (true) {
            $firstOctet >= 1 && $firstOctet <= 9     => 'US',
            $firstOctet >= 24 && $firstOctet <= 30   => 'US',
            $firstOctet >= 41 && $firstOctet <= 42   => 'ZA',
            $firstOctet >= 58 && $firstOctet <= 61   => 'JP',
            $firstOctet >= 77 && $firstOctet <= 95   => 'EU', // 泛欧
            $firstOctet >= 110 && $firstOctet <= 125  => 'CN',
            $firstOctet >= 200 && $firstOctet <= 201  => 'BR',
            default                                    => 'unknown',
        };
    }

    /**
     * 发送退款率预警通知
     */
    private function dispatchRefundAlertNotifications(PaymentAccount $account, array $alerts): void
    {
        foreach ($alerts as $alert) {
            if ($alert['level'] === 'critical') {
                $this->notificationService->send(
                    recipientType: 'admin',
                    recipientId:   1, // 系统管理员
                    title:         "支付账号退款率预警 [CRITICAL]",
                    content:       sprintf(
                        '支付账号 %s（ID: %d）%s 已达 %.2f%%，超过阈值 %.2f%%，请立即处理。',
                        $account->account,
                        $account->id,
                        $alert['type'] === 'refund_rate' ? '退款率' : '争议率',
                        $alert['rate'],
                        $alert['threshold'],
                    ),
                    type:     NotificationService::TYPE_RISK,
                    level:    NotificationService::LEVEL_ERROR,
                    channels: ['database', 'dingtalk'],
                    metadata: [
                        'account_id' => $account->id,
                        'alert_type' => $alert['type'],
                        'rate'       => $alert['rate'],
                    ],
                );
            }
        }
    }
}
