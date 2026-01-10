<?php

namespace App\Gateways\Push;

use App\Helpers\GatewayHelper;
use App\Helpers\LogHelper;
use App\Models\Gateway;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class Firebase implements PushNotificationInterface
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

    public function send(array $params): bool
    {
        try {
            $accessToken = $this->getAccessToken();

            $response = Http::withToken($accessToken)
                ->post(
                    $this->attributes['endpoints']['execute'],
                    [
                        'message' => [
                            'token' => $params['token'],
                            'notification' => $params['notification'],
                            'data' => $params['data'],
                        ]
                    ]
                );

            // dd($response->json());
            LogHelper::debug('FIREBASE_PUSH_NOTIFICATION_SEND_RESPONSE', [
                'response' => $response->json()
            ]);
            return $response->successful();
        } catch (\Exception $exception) {
            LogHelper::exception($exception, [
                'keyword' => 'FIREBASE_PUSH_NOTIFICATION_SEND_EXCEPTION'
            ]);

            // dd($exception);
            return false;
        }
    }

    public
    function getAccessToken(): string
    {
        return Cache::remember('firebase_access_token', 3500, function () {
            $credentials = json_decode(
                file_get_contents(config('firebase.credentials')),
                true
            );

            $now = time();

            $payload = [
                'iss' => $credentials['client_email'] ?? $credentials['client_id'],
                'scope' => 'https://www.googleapis.com/auth/firebase.messaging',
                'aud' => 'https://oauth2.googleapis.com/token',
                'iat' => $now,
                'exp' => $now + 3600,
            ];

            $jwt = $this->encodeJwt($payload, $credentials['private_key']);

            $response = Http::asForm()->post(
                $this->attributes['endpoints']['token'],
                [
                    'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                    'assertion' => $jwt,
                ]
            );

            if (!$response->successful()) {
                throw new \RuntimeException('Firebase auth failed');
            }

            return $response->json('access_token');
        });
    }

    private
    function encodeJwt(array $payload, string $privateKey): string
    {
        $header = ['alg' => 'RS256', 'typ' => 'JWT'];

        $segments = [];
        $segments[] = $this->base64Url(json_encode($header));
        $segments[] = $this->base64Url(json_encode($payload));

        openssl_sign(
            implode('.', $segments),
            $signature,
            $privateKey,
            OPENSSL_ALGO_SHA256
        );

        $segments[] = $this->base64Url($signature);

        return implode('.', $segments);
    }

    private
    function base64Url(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }
}
