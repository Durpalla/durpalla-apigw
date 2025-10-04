<?php

namespace App\Gateways;

use App\Helpers\LogHelper;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class Nagad implements GatewayInterface
{
    private array $credentials = [];

    public function __construct()
    {
        $this->setCredentials();
    }

    private function setCredentials(): void
    {
        $config = config('gateway.nagad');
        if (is_array($config) && !empty($config['env'])) {
            $this->credentials = $config[$config['env']] ?? [];
        }
    }

    public function create($payment, $request, &$data): void
    {
        $data['success'] = false;
        try {
            // 1) Initialize -> receive paymentRefId + challenge
            $init = $this->initialize($payment);

            if (!($init['ok'] ?? false)) {
                $data['message'] = $init['message'] ?? 'Initialization failed';
                return;
            }

            // Persist paymentRefId on your Payment row
            $payment->update(['gateway_trx_id' => $init['paymentRefId']]);

            // 2) Complete (Place Order) -> receive callBackUrl
            $complete = $this->complete($payment, $init['challenge']);

            if (!($complete['ok'] ?? false)) {
                $data['message'] = $complete['message'] ?? 'Order placement failed';
                return;
            }

            // Final data for frontend redirection
            $data['success']   = true;
            $data['status']    = 'success';
            $data['message']   = 'success';
            $data['paymentURL'] = $complete['callBackUrl'];

        } catch (\Throwable $e) {
            $data['message'] = $e->getMessage();
            LogHelper::exception($e, ['keyword' => 'NAGAD_CREATE_EXCEPTION']);
        }
    }

    public function execute($payment, $request, &$data): void
    {
        $this->verify($payment, $request, $data);
    }

    /**
     * Verify status via Nagad "Check Payment Status" endpoint.
     * @see PDF section 5.2.8.3 Check Payment Status
     */
    public function verify($payment, $request, &$data): void
    {
        $data['success'] = false;

        try {
            $paymentRefId = $payment->gateway_trx_id;
            if (!$paymentRefId) {
                $data['message'] = 'Missing payment reference.';
                return;
            }

            $url = $this->buildUrl($this->credentials['endpoints']['verify'], [
                'paymentRefId' => $paymentRefId,
            ]);

            $res = Http::withHeaders($this->baseHeaders())->get($url);

            if (!$res->successful()) {
                $data['message'] = 'Verify request failed';
                return;
            }

            $js = $res->json();
            // status: Success | Failed | ...
            $status = $js['status'] ?? 'UnknownFailed';

            if (Str::lower($status) === 'success') {
                if ($payment->status !== 'success') {
                    $payment->update(['status' => 'success']);
                }
                $data['success'] = true;
                $data['status']  = true;
                $data['message'] = 'Payment successful';
            } else {
                $payment->update(['status' => 'failed']);
                $data['status']  = false;
                $data['message'] = $js['statusCode'] ?? 'Payment failed';
            }
        } catch (\Throwable $e) {
            $data['message'] = $e->getMessage();
            LogHelper::exception($e, ['keyword' => 'NAGAD_VERIFY_EXCEPTION']);
        }
    }

    /** ===== Internals for Nagad flow ===== */

    private function initialize($payment): array
    {
        // Build sensitiveData (plain)
        $merchantId = $this->credentials['merchant_id'];
        $orderId    = (string) $payment->transaction_id;
        $dateTime   = now()->format('YmdHis'); // yyyyMMddHHmmSS
        $challenge  = Str::upper(bin2hex(random_bytes(20))); // 40 hex chars

        $plain = json_encode([
            'merchantId' => $merchantId,
            'dateTime'   => $dateTime,
            'orderId'    => $orderId,
            'challenge'  => $challenge,
        ], JSON_UNESCAPED_SLASHES);

        // Encrypt with Nagad PG public key + Sign with Merchant private key
        $sensitiveData = $this->encryptBase64($plain, $this->credentials['nagad_public_key']);
        $signature     = $this->signBase64($plain,  $this->credentials['merchant_private_key']);

        $url = $this->buildUrl($this->credentials['endpoints']['create'], [
            'merchantID' => $merchantId,
            'orderID'    => $orderId,
        ]);

        // Optional accountNumber (merchant mobile) supported by API
        $payload = [
            'accountNumber' => $this->credentials['merchant_mobile'] ?? null,
            'dateTime'      => $dateTime,
            'sensitiveData' => $sensitiveData,
            'signature'     => $signature,
        ];

        $query = ['locale' => $this->credentials['locale'] ?? 'EN'];

        $res = Http::withHeaders($this->baseHeaders())
            ->asJson()
            ->post($url . (empty($query) ? '' : ('?' . http_build_query($query))), $payload);

        if (!$res->successful()) {
            return ['ok' => false, 'message' => 'Initialization request failed'];
        }

        $js = $res->json();
        if (!isset($js['sensitiveData'], $js['signature'])) {
            return ['ok' => false, 'message' => 'Invalid initialize response'];
        }

        // Decrypt response
        $respPlain = $this->decryptBase64($js['sensitiveData'], $this->credentials['merchant_private_key']);

        // Verify signature using Nagad PG public key
        $verified = $this->verifySignatureBase64($respPlain, $js['signature'], $this->credentials['nagad_public_key']);
        if (!$verified) {
            return ['ok' => false, 'message' => 'Signature verification failed'];
        }

        $resp = json_decode($respPlain, true) ?: [];
        // expect: paymentReferenceId, acceptDateTime, random (this random is the "challenge" for next step)
        $paymentRefId = $resp['paymentReferenceId'] ?? null;
        $nextChallenge = $resp['random'] ?? null;

        if (!$paymentRefId || !$nextChallenge) {
            return ['ok' => false, 'message' => 'Missing paymentReferenceId/challenge'];
        }

        return ['ok' => true, 'paymentRefId' => $paymentRefId, 'challenge' => $nextChallenge];
    }

    private function complete($payment, string $challenge): array
    {
        $merchantId = $this->credentials['merchant_id'];
        $orderId    = (string) $payment->transaction_id;
        $amount     = number_format((float)$payment->paid_amount, 2, '.', '');
        $currency   = $this->credentials['currency_code'] ?? '050';

        $plain = json_encode([
            'merchantId'   => $merchantId,
            'orderId'      => $orderId,
            'amount'       => $amount,
            'currencyCode' => $currency,
            'challenge'    => $challenge,
            // 'otherAmount' => ['serviceFee' => '2.56'], // if you use v-3.0.1 and sender fee
        ], JSON_UNESCAPED_SLASHES);

        $sensitiveData = $this->encryptBase64($plain, $this->credentials['nagad_public_key']);
        $signature     = $this->signBase64($plain,  $this->credentials['merchant_private_key']);

        $url = $this->buildUrl($this->credentials['endpoints']['execute'], [
            'paymentRefId' => $payment->gateway_trx_id,
        ]);

        $payload = [
            'sensitiveData' => $sensitiveData,
            'signature'     => $signature,
            'merchantCallbackURL' => $this->credentials['callback'] ?? null, // strongly recommended
            // 'additionalMerchantInfo' => [
            //     'serviceName' => 'Durpalla Booking',
            //     'serviceLogoURL' => 'https://example.com/logo.png',
            //     'additionalFieldNameEN' => 'Trip',
            //     'additionalFieldNameBN' => 'যাত্রা',
            //     'additionalFieldValue'  => $orderId,
            // ],
        ];

        $res = Http::withHeaders($this->baseHeaders())
            ->asJson()
            ->post($url, $payload);

        if (!$res->successful()) {
            return ['ok' => false, 'message' => 'Complete request failed'];
        }

        $js = $res->json();
        $redirect = $js['callBackUrl'] ?? null;

        if (!$redirect) {
            return ['ok' => false, 'message' => 'Missing callBackUrl'];
        }

        return ['ok' => true, 'callBackUrl' => $redirect];
    }

    /** ===== Utilities ===== */

    private function baseHeaders(): array
    {
        return [
            'Content-Type'    => 'application/json',
            'X-KM-IP-V4'      => request()->ip() ?? '127.0.0.1',
            'X-KM-Client-Type'=> $this->credentials['client_type'] ?? 'PC_WEB',
            'X-KM-Api-Version'=> $this->credentials['version'] ?? 'v-0.2.0',
        ];
    }

    private function buildUrl(string $pattern, array $vars): string
    {
        $base = rtrim($this->credentials['base_url'] ?? '', '/');
        foreach ($vars as $k => $v) {
            $pattern = str_replace('{' . $k . '}', $v, $pattern);
        }
        return $base . $pattern;
    }

    private function encryptBase64(string $plain, string $publicKeyPem): string
    {
        $pub = openssl_pkey_get_public($publicKeyPem);
        if (!$pub) {
            throw new \RuntimeException('Invalid Nagad public key');
        }
        $ok = openssl_public_encrypt($plain, $cipher, $pub, OPENSSL_PKCS1_PADDING);
        if (!$ok) {
            throw new \RuntimeException('Nagad encryption failed');
        }
        return base64_encode($cipher);
    }

    private function decryptBase64(string $b64Cipher, string $privateKeyPem): string
    {
        $priv = openssl_pkey_get_private($privateKeyPem);
        if (!$priv) {
            throw new \RuntimeException('Invalid merchant private key');
        }
        $cipher = base64_decode($b64Cipher, true);
        $ok = openssl_private_decrypt($cipher, $plain, $priv, OPENSSL_PKCS1_PADDING);
        if (!$ok) {
            throw new \RuntimeException('Nagad decryption failed');
        }
        return $plain;
    }

    private function signBase64(string $plain, string $privateKeyPem): string
    {
        $priv = openssl_pkey_get_private($privateKeyPem);
        if (!$priv) {
            throw new \RuntimeException('Invalid merchant private key');
        }
        $ok = openssl_sign($plain, $sig, $priv, OPENSSL_ALGO_SHA1); // SHA1withRSA
        if (!$ok) {
            throw new \RuntimeException('Signing failed');
        }
        return base64_encode($sig);
    }

    private function verifySignatureBase64(string $plain, string $b64Sig, string $publicKeyPem): bool
    {
        $pub = openssl_pkey_get_public($publicKeyPem);
        if (!$pub) {
            return false;
        }
        $sig = base64_decode($b64Sig, true);
        return openssl_verify($plain, $sig, $pub, OPENSSL_ALGO_SHA1) === 1;
    }

    public function refund($payment, $request)
    {
        return ['message' => 'Not implemented'];
    }

    private function formatPemKey(string $key, string $type = 'PRIVATE'): string
    {
        // If key already has proper PEM headers, return as-is
        if (Str::contains($key, 'BEGIN')) {
            return $key;
        }

        // Add PEM headers/footers and line breaks every 64 chars
        $formatted = "-----BEGIN {$type} KEY-----\n" .
            trim(chunk_split($key, 64, "\n")) .
            "-----END {$type} KEY-----";

        return $formatted;
    }
}
