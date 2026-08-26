<?php

namespace App\Constants;

class GatewayConstant
{
    public const ACTIVE = 1;
    public const INACTIVE = 0;

    public const CHANNEL_OFFLINE = 'offline';
    public const CHANNEL_LIVE = 'live';
    public const CHANNEL_MERCHANT = 'merchant';

    public const CODE_CASH = 'cash';
    public const CODE_FUND = 'fund';
    public const CODE_BANK_CHECK = 'bank_check';
    public const CODE_BANK_TRANSFER = 'bank_transfer';
    public const CODE_BKASH = 'bkash';
    public const CODE_NAGAD = 'nagad';
    public const CODE_ROCKET = 'rocket';
    public const CODE_CARD = 'card';
    public const CODE_HTTP_SMS = 'http_sms';

    public const TYPE_PAYMENT = 'payment';
    public const TYPE_SMS = 'sms';
    public const TYPE_NOTIFICATION = 'notification';
    public const TYPE_WHATSAPP = 'whatsapp';

    public const TYPE_CARD_PURCHASE = 'bundle-purchase';
    public const TYPE_BILL_PAYMENT = 'bill-payment';
    public const GATEWAY_STOCK = 1;

    /** @return list<string> */
    public static function channels(): array
    {
        return [
            self::CHANNEL_OFFLINE,
            self::CHANNEL_LIVE,
            self::CHANNEL_MERCHANT,
        ];
    }

    /** Gateway purpose: payment PG vs messaging / push providers. @return list<string> */
    public static function types(): array
    {
        return [
            self::TYPE_PAYMENT,
            self::TYPE_SMS,
            self::TYPE_NOTIFICATION,
            self::TYPE_WHATSAPP,
        ];
    }

    /** @return array<string, string> */
    public static function typeLabels(): array
    {
        return [
            self::TYPE_PAYMENT => 'Payment',
            self::TYPE_SMS => 'SMS',
            self::TYPE_NOTIFICATION => 'Notification (Push)',
            self::TYPE_WHATSAPP => 'WhatsApp',
        ];
    }

    /** Types merchants may enable and manage from templates. @return list<string> */
    public static function merchantManageableTypes(): array
    {
        return [
            self::TYPE_PAYMENT,
            self::TYPE_SMS,
        ];
    }

    /** @return array<string, string> */
    public static function merchantTypeLabels(): array
    {
        $all = self::typeLabels();
        $out = [];
        foreach (self::merchantManageableTypes() as $type) {
            $out[$type] = $all[$type] ?? ucfirst($type);
        }

        return $out;
    }
}
