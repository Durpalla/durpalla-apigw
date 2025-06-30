<?php


namespace App\Services;


use App\Gateway\BkashInterface;
use App\Gateway\GatewayInterface;

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
