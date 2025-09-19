<?php


namespace App\Services;


use App\Gateways\GatewayInterface;

class PaymentService implements PaymentInterface
{
    public function token(GatewayInterface $gateway, object $order)
    {
        return $gateway->token($order);
    }

    public function create(GatewayInterface $gateway, array $data)
    {
        return $gateway->create($data);
    }

    public function execute(GatewayInterface $gateway, array $params)
    {
        return $gateway->execute($params);
    }

    public function intend(GatewayInterface $gateway)
    {
        // TODO: Implement intend() method.
    }
}
