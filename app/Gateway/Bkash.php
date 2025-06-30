<?php


namespace App\Gateway;
use App\Jobs\BkashBookingCompleteJob;

class Bkash extends Builder implements GatewayInterface, BkashInterface
{
    public function __construct()
    {
//        config()->set('sslcommerz.sandbox_mode', getOption('Bkash_gateway_sandbox', config('sslcommerz.sandbox_mode')));
//        config()->set('sslcommerz.store_id', getOption('Bkash_app_id', config('sslcommerz.store_id')));
//        config()->set('sslcommerz.store_password', getOption('Bkash_store_password', config('sslcommerz.store_password')));
    }

    public function token($order)
    {
        $incentive = $order->bookingItems->map(function($item, $key) {
            return [
                'incentive' => ($item->incentive_type === 'percent') ? ($item->price * ($item->incentive / 100)) : $item->incentive
            ];
        })->sum('incentive');
        $data = ['status' => false, 'order' => $order->id, 'invoice' => $order->payment->transaction_id, 'amount' => $order->total_payable - $incentive];
        $response = $this->__setUrl(getOption('Bkash_gateway_url') . 'token/grant')
            ->__setBody(json_encode([
                'app_key' => getOption('Bkash_app_id'),
                'app_secret' => getOption('Bkash_secret_key')
            ]))
            ->__setHeader(array(
                'Content-Type:application/json',
                'password:' . getOption('Bkash_store_password'),
                'username:' . getOption('Bkash_app_username')
            ))->_call();
        $response = json_decode($response);
        if (!is_object($response) || !$response->id_token) {
            $data['message'] = 'Token error';
        } else {
            $data['status'] = true;
            $data['token'] = $response->id_token;
        }
        return response()->json($data);
    }

    public function create($params)
    {
        return $this->__setHeader([
            'Content-Type:application/json',
            'authorization:' . $params['token'],
            'x-app-key:' . getOption('Bkash_app_id')
        ])
            ->__setBody(json_encode(['amount' => $params['amount'], 'currency' => 'BDT', 'merchantInvoiceNumber' => $params['invoice'], 'intent' => 'sale']))
            ->__setUrl(getOption('Bkash_gateway_url') . 'payment/create')
            ->_call();
    }

    public function execute($params)
    {
        $result = $this->__setUrl(getOption('Bkash_gateway_url') . 'payment/execute/' . $params['paymentID'])
            ->__setHeader([
                'Content-Type:application/json',
                'authorization:' . $params['token'],
                'x-app-key:' . getOption('Bkash_app_id')
            ])
            ->__setBody(null)
            ->_call();
            dispatch(new BkashBookingCompleteJob($result));
        return $result;
    }

    public function intend()
    {

    }

}
