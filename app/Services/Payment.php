<?php


namespace App\Services;


use App\Gateways\BkashInterface;
use App\Gateways\GatewayInterface;

class Payment implements PaymentInterface
{
    /**
     * @var GatewayInterface
     */
    private $gateway;
    /**
     * @var BkashInterface
     */
    private $bkash;

    public function __construct(GatewayInterface $gateway, BkashInterface $bkash)
    {
        $this->gateway = $gateway;
        $this->bkash = $bkash;
    }

    public function create()
    {

    }

    public function intend()
    {

    }
}
