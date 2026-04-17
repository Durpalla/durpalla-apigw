<?php

namespace App\Gateways;

class Sslcom extends Builder implements GatewayInterface
{
    public function __construct()
    {

    }

    public function token($payment, $request)
    {

    }

    public function create($payment, $request, &$data): void
    {
        $data['success'] = false;
        $data['message'] = __('SSL Commerz integration is not wired in the API gateway. Use a gateway whose handler is implemented (e.g. bKash).');
    }

    public function execute($payment, $request, &$data): void
    {
        $data['success'] = false;
        $data['message'] ??= __('SSL Commerz execute is not implemented.');
    }

    public function intend()
    {

    }

    public function verify($payment, $request, &$data): void
    {
        $data['success'] = false;
    }
}
