<?php


namespace App\Gateway;


interface GatewayInterface
{
    public function token( $order );
}
