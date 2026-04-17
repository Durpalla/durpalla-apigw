<?php

namespace App\Gateways;

use App\Helpers\LogHelper;
use App\Models\Gateway;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use App\Helpers\GatewayHelper;

class Bkash implements GatewayInterface, BkashInterface
{
    private Gateway $gateway;
    private array $attributes;

    public function __construct(Gateway $gateway)
    {
        $this->gateway = $gateway;
        $this->setCredentials();
    }

    public function setCredentials(): void
    {
        $cacheKey = Str::slug($this->gateway->name);
        $attributes = Cache::get($cacheKey, []);
        if (empty($attributes)) {
            $attributes = GatewayHelper::getCredentials($this->gateway);

            Cache::put($cacheKey, $attributes);
        }

        $this->attributes = $attributes;
    }

    private function authHeaders(): array
    {
        return [
            'Accept' => 'application/json',
            'Authorization' => $this->token(),
            'X-App-Key' => $this->attributes['credentials']['app_key'],
        ];
    }

    public function create($payment, $request, &$data)
    {
        try {
            $payload = [
                'mode' => '0011',
                'callbackURL' => route('gateway.callback', $payment->gateway_id),
                'amount' => $payment->paid_amount,
                'currency' => $this->attributes['params']['currency'] ?? 'BDT',
                'intent' => 'sale',
                'merchantInvoiceNumber' => $payment->transaction_id,
                'payerReference' => '01770618575'
            ];

            $res = Http::withHeaders($this->authHeaders())
                ->asJson()
                ->post($this->attributes['endpoints']['create'], $payload);

            if ($res->successful()) {
                $jsonData = $res->json();
                LogHelper::debug('BKASH_CREATE_PAYMENT_RESPONSE', [
                    'response' => (!app()->environment('production')) ? $jsonData : Arr::except($jsonData, ['bkashURL'])
                ]);
                if (is_array($jsonData) && array_key_exists('paymentID', $jsonData)) {
                    $payment->update(['gateway_initiated_id' => $jsonData['paymentID']]);
                    $data['status'] = 'success';
                    $data['success'] = true;
                    $data['message'] = 'success';
                    $data['paymentID'] = $jsonData['paymentID'];
                    $data['paymentURL'] = $jsonData['bkashURL'];
                } else {
                    $data['status'] = false;
                    $data['message'] = 'Gateway not initiated payment. Please try again or contact the developer for further assistance.';
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
                ->post($this->attributes['endpoints']['execute'], [
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
            ->post($this->attributes['endpoints']['verify'], [
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
                        'username' => $this->attributes['credentials']['username'],
                        'password' => $this->attributes['credentials']['password'],
                    ])
                    ->post($this->attributes['endpoints']['token'], [
                        'app_key' => $this->attributes['credentials']['app_key'],
                        'app_secret' => $this->attributes['credentials']['app_secret'],
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
            ->post($this->attributes['endpoints']['refund'], [
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
