<?php

namespace App\Gateways;

interface GatewayInterface
{
    public function create($payment, $request, &$data);

    public function execute($payment, $request, &$data);

    public function verify($payment, $request, &$data);
}
