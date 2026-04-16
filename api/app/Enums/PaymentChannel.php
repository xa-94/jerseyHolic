<?php

namespace App\Enums;

enum PaymentChannel: int
{
    case OTHER = 0;
    case PAYPAL = 1;
    case SKRPAY = 2;
    case PAYPAL_INVOICE = 3;
    case CREDIT_CARD = 4;
    case PAYPAL_JHPAY = 5;
    case STRIPE = 6;
    case ANTOM_CARD = 7;
    case AIRWALLEX = 8;
    case ANTOM_IDEAL = 9;
    case ANTOM_BANCOMAT = 10;
    case ANTOM_BLIK = 11;
    case ANTOM_BANCONTACT = 12;
    case ANTOM_KAKAO = 13;
    case PAYSSION = 14;
    case ANTOM = 15;
    case STARLINK = 16;

    public function label(): string
    {
        return match ($this) {
            self::OTHER => 'Other',
            self::PAYPAL => 'PayPal',
            self::SKRPAY => 'SkrPay',
            self::PAYPAL_INVOICE => 'PayPal Invoice',
            self::CREDIT_CARD => 'Credit Card',
            self::PAYPAL_JHPAY => 'PayPal JHPay',
            self::STRIPE => 'Stripe',
            self::ANTOM_CARD => 'Antom Card',
            self::AIRWALLEX => 'Airwallex',
            self::ANTOM_IDEAL => 'Antom iDEAL',
            self::ANTOM_BANCOMAT => 'Antom Bancomat',
            self::ANTOM_BLIK => 'Antom Blik',
            self::ANTOM_BANCONTACT => 'Antom Bancontact',
            self::ANTOM_KAKAO => 'Antom Kakao',
            self::PAYSSION => 'Payssion',
            self::ANTOM => 'Antom',
            self::STARLINK => 'Starlink',
        };
    }
}
