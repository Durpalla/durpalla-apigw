<?php

namespace App\Helpers;

use App\Gateways\NotExist;
use App\Models\Gateway;

class GatewayHelper
{
    public static function getCredentials(Gateway $gateway): array
    {
        $credentials = [];
        foreach ($gateway->credentials as $credential) {
            $credentials['credentials'][$credential->key] = $credential->value;
        }

        foreach ($gateway->params as $param) {
            $credentials['params'][$param->key] = $param->value;
        }

        foreach ($gateway->endpoints as $endpoint) {
            $credentials['endpoints'][$endpoint->key] = $endpoint->value;
        }

        if (!app()->environment('production')) {
            LogHelper::info('credentials', [
                'credentials' => $credentials,
            ]);
        }
        return $credentials;
    }

    public static function purseGateway($gateway): string
    {
        $gatewayName = $gateway->class_name ?? NotExist::class;

        if (!class_exists($gatewayName)) {
            $gatewayName = "App\Gateways\NotExist";
        }

        LogHelper::debug("GATEWAY_NAME_FROM_VALIDATION_HELPER", [
            'gateway-name' => $gatewayName
        ]);

        return $gatewayName;
    }
}
