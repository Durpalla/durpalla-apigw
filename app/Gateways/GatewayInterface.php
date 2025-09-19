<?php


namespace App\Gateways;


interface GatewayInterface
{
    public function token( $order );
}
