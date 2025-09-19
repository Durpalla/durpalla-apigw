<?php


namespace App\Services;


use App\Gateways\GatewayInterface;

interface PaymentInterface
{
    public function token(GatewayInterface $gateway, object $order);
    public function create(GatewayInterface $gateway, array $data);
    public function execute(GatewayInterface $gateway, array $params);
    public function intend(GatewayInterface $gateway);
}
