<?php

declare(strict_types=1);

namespace App\Services\Payment;

use App\Models\Central\PaymentAccount;

/**
 * 支付网关统一接口
 *
 * PayPal、Stripe 等支付渠道的公共抽象。
 * 所有实现均需通过 SafeDescriptionService 对商品信息脱敏。
 */
interface PaymentGatewayInterface
{
    /**
     * 创建支付订单
     *
     * @param  array          $orderData  订单数据（items, amount, currency, return_url, cancel_url 等）
     * @param  PaymentAccount $account    选号结果的支付账号
     * @return array          网关返回的订单信息（含 order_id, approve_url 等）
     */
    public function createOrder(array $orderData, PaymentAccount $account): array;

    /**
     * 捕获（扣款）已批准的订单
     *
     * @param  string         $orderId  网关侧订单 ID
     * @param  PaymentAccount $account  对应支付账号
     * @return array          捕获结果（含 capture_id, status, amount 等）
     */
    public function captureOrder(string $orderId, PaymentAccount $account): array;

    /**
     * 退款（全额或部分）
     *
     * @param  string         $captureId  捕获 ID
     * @param  string         $amount     退款金额（bcmath 精度字符串）
     * @param  string         $currency   货币代码（如 USD）
     * @param  PaymentAccount $account    对应支付账号
     * @return array          退款结果（含 refund_id, status 等）
     */
    public function refundCapture(string $captureId, string $amount, string $currency, PaymentAccount $account): array;

    /**
     * 查询订单状态
     *
     * @param  string         $orderId  网关侧订单 ID
     * @param  PaymentAccount $account  对应支付账号
     * @return array          订单状态信息
     */
    public function getOrderStatus(string $orderId, PaymentAccount $account): array;
}
