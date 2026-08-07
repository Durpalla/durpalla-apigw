<?php

namespace App\Gateways;

use App\Helpers\LogHelper;
use App\Models\Gateway;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
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
        $this->attributes = GatewayHelper::getCredentials($this->gateway);
    }

    private function authHeaders(): array
    {
        return [
            'Accept' => 'application/json',
            'Authorization' => $this->token(),
            'X-App-Key' => $this->attributes['credentials']['app_key'],
        ];
    }

    public function create($payment, $request, &$data): void
    {
        $this->runCreatePayment($payment, $data);

        // Stale id_token in cache often surfaces as bKash "Unauthorized"; refresh once.
        if (empty($data['success']) && $this->wasAuthRelatedFailure((string) ($data['message'] ?? ''))) {
            Cache::forget('bkash.token');
            $this->runCreatePayment($payment, $data);
        }

        if (empty($data['success']) && $this->isGenericUnauthorizedMessage($data['message'] ?? '')) {
            $data['message'] = __(
                'bKash rejected the request (not logged out of the app). Usually the gateway token expired or sandbox/live credentials are wrong. Please try again; if it keeps happening, check bKash credentials in admin.'
            );
        }
    }

    private function runCreatePayment($payment, &$data): void
    {
        try {
            if (
                empty($this->attributes['endpoints']['create'])
                || empty($this->attributes['credentials']['app_key'])
            ) {
                $data['message'] = __('bKash gateway is not configured. Check credentials and endpoints in admin.');

                return;
            }

            $payload = [
                'mode' => '0011',
                // Absolute HTTPS callback — Android WebView rejects cleartext HTTP.
                'callbackURL' => secure_url(route('gateway.callback', $payment->gateway_id, absolute: false)),
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
                if (is_array($jsonData) && ! empty($jsonData['paymentID'])) {
                    $paymentUrl = $this->resolvePaymentUrl($jsonData);
                    $payment->update(['gateway_initiated_id' => $jsonData['paymentID']]);
                    $data['data']['paymentID'] = $jsonData['paymentID'];
                    if ($paymentUrl) {
                        $data['success'] = true;
                        $data['message'] = 'success';
                        $data['data']['status'] = 'success';
                        $data['data']['paymentURL'] = $paymentUrl;
                    } else {
                        $data['data']['status'] = false;
                        $data['message'] = 'Gateway not initiated payment. Please try again or contact the developer for further assistance.';
                    }
                } else {
                    $data['data']['status'] = false;
                    $data['message'] = 'Gateway not initiated payment. Please try again or contact the developer for further assistance.';
                }
            } else {
                $jsonData = $res->json();
                $data['data']['status'] = false;
                if (is_array($jsonData)) {
                    $data['message'] = (string) ($jsonData['errorMessage']
                        ?? $jsonData['statusMessage']
                        ?? $jsonData['message']
                        ?? '');
                }
                if (($data['message'] ?? '') === '') {
                    if (in_array($res->status(), [401, 403], true)) {
                        $data['message'] = 'Unauthorized';
                    } else {
                        $data['message'] = __('Payment gateway request failed (HTTP :status).', ['status' => $res->status()]);
                    }
                }
                LogHelper::debug('BKASH_CREATE_PAYMENT_HTTP_ERROR', [
                    'status' => $res->status(),
                    'response' => (! app()->environment('production')) ? $res->body() : null,
                ]);
            }
        } catch (\Exception $e) {
            $data['message'] = $e->getMessage();
            LogHelper::exception($e, [
                'keyword' => 'BKASH_CREATE_PAYMENT_EXCEPTION',
            ]);
        }
    }

    private function wasAuthRelatedFailure(string $message): bool
    {
        $m = strtolower($message);

        return str_contains($m, 'unauthorized')
            || str_contains($m, 'permission denied')
            || str_contains($m, 'invalid id token')
            || str_contains($m, 'token expired')
            || str_contains($m, 'authorization header requires');
    }

    private function isGenericUnauthorizedMessage(string $message): bool
    {
        return strtolower(trim($message)) === 'unauthorized';
    }

    /**
     * Tokenized create returns bkashURL; Checkout API returns paymentID + hash only.
     */
    private function resolvePaymentUrl(array $jsonData): ?string
    {
        $direct = $jsonData['bkashURL'] ?? $jsonData['redirectURL'] ?? null;
        if (is_string($direct) && $direct !== '') {
            return $direct;
        }

        $paymentId = $jsonData['paymentID'] ?? null;
        $hash = $jsonData['hash'] ?? null;
        if (! is_string($paymentId) || $paymentId === '' || ! is_string($hash) || $hash === '') {
            return null;
        }

        $createEndpoint = (string) ($this->attributes['endpoints']['create'] ?? '');
        $host = str_contains($createEndpoint, 'sandbox')
            ? 'https://sandbox.payment.bkash.com/'
            : 'https://payment.bkash.com/';
        $apiVersion = (string) ($this->attributes['params']['version'] ?? 'v1.2.0-beta');

        // bKash validates hash literally — do not URL-encode paymentId/hash.
        return $host.'?paymentId='.$paymentId
            .'&hash='.$hash
            .'&mode=0011&apiVersion='.$apiVersion.'/';
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
                        $payment->successful();
                    }
                } else {
                    $data['message'] = $jsonData['statusMessage'];
                    $payment->failed();
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
                    $json = $res->json();
                    if (is_array($json) && array_key_exists('id_token', $json)) {
                        $token = $json['id_token'];
                        Cache::put(
                            'bkash.token',
                            $token,
                            now()->addSeconds((int) ($json['expires_in'] ?? 3500))
                        );
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

    public function refund($payment, $request, $amount = null)
    {
        $refundAmount = $amount ?? $payment->paid_amount ?? $payment->amount ?? 0;
        $paymentId = $payment->gateway_initiated_id ?? $payment->gateway_trx_id ?? null;
        $trxId = $payment->bank_tran_id ?? $payment->transaction_id ?? $payment->uuid ?? null;

        $res = Http::withHeaders($this->authHeaders())
            ->post($this->attributes['endpoints']['refund'], [
                'paymentID' => $paymentId,
                'trxID' => $trxId,
                'amount' => (string) $refundAmount,
                'sku' => (string) $payment->id,
                'reason' => 'Customer request',
            ]);

        return $res->json();
    }

    public function forgetToken(): void
    {
        Cache::forget('bkash.token');
    }
}
