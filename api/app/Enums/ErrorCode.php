<?php

namespace App\Enums;

enum ErrorCode: int
{
    case SUCCESS = 0;
    case PARAM_ERROR = 42200;
    case UNAUTHORIZED = 40100;
    case FORBIDDEN = 40300;
    case NOT_FOUND = 40400;
    case BUSINESS_ERROR = 50000;
    case PAYMENT_ERROR = 50100;
    case LOGISTICS_ERROR = 50200;
    case MAPPING_ERROR = 50300;
    case ACCOUNT_DISABLED = 50400;
    case RATE_LIMIT = 42900;

    /**
     * 获取错误码对应的默认消息
     */
    public function message(): string
    {
        return match ($this) {
            self::SUCCESS => 'success',
            self::PARAM_ERROR => '参数错误',
            self::UNAUTHORIZED => '未认证',
            self::FORBIDDEN => '无权限',
            self::NOT_FOUND => '资源不存在',
            self::BUSINESS_ERROR => '业务错误',
            self::PAYMENT_ERROR => '支付错误',
            self::LOGISTICS_ERROR => '物流错误',
            self::MAPPING_ERROR => '映射错误',
            self::ACCOUNT_DISABLED => '账号已禁用',
            self::RATE_LIMIT => '请求频率超限',
        };
    }
}
