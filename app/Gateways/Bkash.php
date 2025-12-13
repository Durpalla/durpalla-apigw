<?php

namespace App\Gateways;

use App\Constants\AppConst;
use App\Helpers\LogHelper;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class Bkash implements GatewayInterface, BkashInterface
{
    private array $credentials = [];

    public function __construct()
    {
        $this->setCredentials();
    }

    private function setCredentials(): void
    {
        $credentials = config('gateway.bkash');
        if (!empty($credentials) && is_array($credentials)) {
            $this->credentials = $credentials[$credentials['env']];
        }
    }

    private function authHeaders(): array
    {
        return [
            'Accept' => 'application/json',
            'Authorization' => $this->token(),
            'X-App-Key' => $this->credentials['app_key'],
        ];
    }

    public function create($payment, $request, &$data)
    {
        try {
            $payload = [
                'mode' => '0011',
                'callbackURL' => route('gateway.callback', $payment->gateway_id),
                'amount' => $payment->paid_amount,
                'currency' => $this->credentials['currency'],
                'intent' => 'sale',
                'merchantInvoiceNumber' => $payment->transaction_id,
                'payerReference' => '01770618575'
            ];

            $res = Http::withHeaders($this->authHeaders())
                ->asJson()
                ->post($this->credentials['endpoints']['create'], $payload);

            if ($res->successful()) {
                $jsonData = $res->json();

                if (is_array($jsonData) && array_key_exists('paymentID', $jsonData)) {
                    $payment->update(['gateway_initiated_id' => $jsonData['paymentID']]);
                    $data['status'] = 'success';
                    $data['success'] = true;
                    $data['message'] = 'success';
                    $data['paymentID'] = $jsonData['paymentID'];
                    $data['paymentURL'] = $jsonData['bkashURL'];
                } else {
                    $data['status'] = false;
                }
            }
        } catch (\Exception $e) {
            $data['message'] = $e->getMessage();
            LogHelper::exception($e, [
                'keyword' => 'BKASH_CREATE_PAYMENT_EXCEPTION',
            ]);
        }
    }

    public function execute($payment, $request, &$data)
    {
        try {
            $res = Http::withHeaders($this->authHeaders())
                ->post($this->credentials['endpoints']['execute'], [
                    'paymentID' => $request->input('paymentID')
                ]);

            if ($res->successful()) {
                $jsonData = $res->json();

                if (is_array($jsonData)) {
                    if ($jsonData['statusCode'] == '2062') {
                        $data['status'] = true;
                        $data['message'] = __('Payment has already been succeeded.');
                        $payment->successful();
                    } else {
                        $data['message'] = $jsonData['statusMessage'];
                        if (array_key_exists('paymentID', $jsonData)) {
                            $data['status'] = true;
                            $data['message'] = __('Payment successful');
                            $payment->successful();
                        } else {
                            $data['status'] = false;
                            $data['message'] = $jsonData['statusMessage'] ?? __('Payment failed');
                            $payment->failed();
                        }
                    }
                }
            }
        } catch (\Exception $e) {
            $data['message'] = $e->getMessage();
            LogHelper::exception($e, [
                'keyword' => 'BKASH_EXEC_PAYMENT_EXCEPTION',
            ]);
        }
    }

    public function verify($payment, $request, &$data): void
    {
        $res = Http::withHeaders($this->authHeaders())
            ->post($this->credentials['endpoints']['verify'], [
                'paymentID' => $payment->gateway_trx_id,
            ]);

        if ($res->successful()) {
            $jsonData = $res->json();
            $data['message'] = $jsonData['statusMessage'];
            if (is_array($jsonData) && array_key_exists('paymentID', $jsonData)) {
                if ($jsonData['statusCode'] == '0000') {
                    if ($payment->status != 'success') {
                        $payment->update(['status' => 'success']);
                    }
                } else {
                    $data['message'] = $jsonData['statusMessage'];
                    $payment->update(['status' => 'failed']);
                }
            }
        }
    }

    public function token()
    {
        $token = Cache::get('bkash.token');
        try {
            if (empty($token)) {
                $res = Http::asJson()
//            ->withOptions(['debug' => true])
                    ->withHeaders([
                        'username' => $this->credentials['username'],
                        'password' => $this->credentials['password'],
                    ])
                    ->post($this->credentials['endpoints']['token'], [
                        'app_key' => $this->credentials['app_key'],
                        'app_secret' => $this->credentials['app_secret'],
                    ]);

                if ($res->successful()) {
                    if (array_key_exists('id_token', $res->json())) {
                        $token = $res->json()['id_token'];
                        Cache::put('bkash.token', $token, now()->addSeconds($res->json()['expires_in'] ?? 3500));
                    }
                }
            }
        } catch (\Exception $exception) {
            LogHelper::exception($exception, [
                'keyword' => 'BKASH_TOKEN_EXCEPTION',
            ]);
        }

        return $token;
    }

    public function refund($payment, $request)
    {
        $res = Http::withHeaders($this->authHeaders())
            ->post($this->credentials['endpoints']['refund'], [
                'paymentID' => $payment->gateway_trx_id,
                'trxID' => $payment->uuid,
                'amount' => $payment->amount,
                'sku' => $payment->id,
                'reason' => 'Customer request',
            ]);

        return $res->json();
    }

    public function forgetToken(): void
    {
        Cache::forget('bkash_token');
    }
}
