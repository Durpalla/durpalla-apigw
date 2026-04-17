<?php

namespace App\Gateways;

/**
 * Fallback when gateways.class_name is missing or invalid (avoids fatal "undefined method create").
 */
class NotExist implements GatewayInterface
{
    public function create($payment, $request, &$data): void
    {
        $data['success'] = false;
        $data['message'] = __('Payment gateway is not configured (invalid or missing class_name).');
    }

    public function execute($payment, $request, &$data): void
    {
        $data['success'] = false;
    }

    public function verify($payment, $request, &$data): void
    {
        $data['success'] = false;
    }
}
