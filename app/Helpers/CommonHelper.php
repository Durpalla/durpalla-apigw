<?php

namespace App\Helpers;

use App\Channels\FcmChannel;
use App\Channels\SmsChannel;
use App\Gateways\GatewayInterface;
use App\Gateways\NotExist;
use App\Models\Customer;
use App\Models\User;
use Illuminate\Support\Str;
use App\Models\Cart;

class CommonHelper
{
    public static function purseGateway($gateway): GatewayInterface
    {
        $gatewayName = $gateway->class_name ?? NotExist::class;
        if (!class_exists($gatewayName)) {
            throw new \Exception('Gateway not properly configured', 500);
        }

        // Some gateways (e.g. Bkash) require the Gateway model in the constructor;
        // others (Nagad, Sslcom) use zero-arg constructors.
        $ref = new \ReflectionClass($gatewayName);
        $ctor = $ref->getConstructor();
        if ($ctor === null || $ctor->getNumberOfRequiredParameters() === 0) {
            return $ref->newInstance();
        }

        return $ref->newInstance($gateway);
    }

    public static function hasPermission(array $permissions, $roles = ['admin']): bool
    {
        $user = request()->user();
        return $user->hasAnyRole($roles) || $user->canAny($permissions);
    }

    public static function getGatewayIds(): array
    {
        return explode(',', str_replace(' ', '', getOption('gateway_user_ids'))) ?? [];
    }

    public function checkNid($nid, $dob)
    {
        $url = config('porichoy.live.autofill_v2_new');

        $curl = curl_init();

        curl_setopt_array($curl, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_POSTFIELDS => '{
                "nidNumber": "' . $nid . '",
                "englishTranslation": true,
                "dateOfBirth": "' . $dob . '"
            }',
            CURLOPT_HTTPHEADER => [
                'Accept: application/json',
                'Content-Type: application/json',
                'x-api-key: ' . config('porichoy.key'),
            ],
        ]);

        $response = curl_exec($curl);
        curl_close($curl);

        $data = json_decode($response, true, 512, JSON_THROW_ON_ERROR);

        dd($data);
    }

    public static function customerDues($dues): int
    {
        return $dues > 0 ? self::numberFormat($dues) : 0;
    }

    public static function customerAdvance($dues): int
    {
        return $dues < 0 ? self::numberFormat($dues) : 0;
    }

    public static function parseTemplate($string, $array)
    {
        foreach ($array as $key => $value) {
            $string = preg_replace("/{" . $key . "}/i", $value, $string);
        }

        return $string;
    }

    public static function _customerIDExist($id, $customerID): bool
    {
        return (bool)Customer::where('id', '!=', $id)->where('customerID', $customerID)->count();
    }

    public static function _mobileExist($id, $mobile): bool
    {
        return (bool)Customer::where('id', '!=', $id)->where('primary_contact', $mobile)->count();
    }

    public static function _usernameExist($userId, $username): bool
    {
        return (bool)User::where('id', '!=', $userId)->where('username', $username)->count();
    }

    public static function _emailExist($userId, $email): bool
    {
        return (bool)User::where('id', '!=', $userId)->where('email', $email)->count();
    }

    public static function numberFormat($number)
    {
        return self::{getOption('number_format', 'ceil')}($number);
    }

    public function displayFormat($number): string
    {
        return number_format($number, 2);
    }

    public static function customerStatus($status)
    {
        return config('common.customer.statuses')[$status];
    }

    public static function getMethodName($request): string
    {
        return explode('@', (string)$request->route()->getActionName())[1] ?? 'info';
    }


    public static function calculatePercentage($commission_amount, $total_amount)
    {
        if ($commission_amount && $total_amount) {
            return self::numberFormat((($commission_amount / $total_amount) * 100));
        } else {
            return 0;
        }
    }

    public static function calculateCommissionFromAmount($amount, $percentage)
    {
        if ($amount && $percentage) {
            return self::numberFormat(($percentage / 100) * $amount);
        } else {
            return 0;
        }
    }

    protected static function ceil($number): float
    {
        return ceil($number);
    }

    protected static function round($number): float
    {
        return round($number, 0);
    }

    protected static function floor($number): float
    {
        return floor($number);
    }

    public static function strtoln($string, $replace = ''): string
    {
        return str_replace(PHP_EOL, $replace, $string);
    }

    public static function generateUniqueUUID(): string
    {
        $uuid = (string) Str::uuid();
        if (app()->environment('testing')) {
            return encrypt($uuid);
        }
        try {
            for (;;) {
                if (Cart::where('token', $uuid)->count() === 0) {
                    break;
                }
                $uuid = (string) Str::uuid();
            }
        } catch (\Throwable) {
            // DB unavailable: still return a guest id (EnsureGuestId uses the same pattern).
        }

        return encrypt($uuid);
    }

    public static function getNotificationChannels(): array
    {
        $options = getOption('notification_channels', ['fcm']);
        $channels = [];
        foreach ($options as $key => $channel) {
            switch ($channel) {
                case 'mail' :
                    $channels[] = 'mail';
                    break;
                case 'sms' :
                    $channels[] = SmsChannel::class;
                    break;
                case 'fcm' :
                    $channels[] = FcmChannel::class;
                    break;
            }
        }
        return $channels;
    }
}
