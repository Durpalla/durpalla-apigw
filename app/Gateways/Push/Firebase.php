<?php

namespace App\Gateways\Push;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class Firebase implements PushNotificationInterface
{
    private array $credentials = [];

    public function __construct()
    {
        $this->setCredentials();
    }

    private function setCredentials(): void
    {
        $credentials = config('gateway.firebase');
        if (!empty($credentials) && is_array($credentials)) {
            $this->credentials = $credentials[$credentials['env']];
        }
    }

    public function send(string $token, string $title, string $body, array $data = []): void
    {
        $accessToken = $this->getAccessToken();

        Http::withToken($accessToken)
            ->post(
                "https://fcm.googleapis.com/v1/projects/" .
                config('firebase.project_id') .
                "/messages:send",
                [
                    'message' => [
                        'token' => $token,
                        'notification' => [
                            'title' => $title,
                            'body'  => $body,
                        ],
                        'data' => $data,
                    ],
                ]
            )
            ->throw();
    }

    public function getAccessToken(): string
    {
        return Cache::remember('firebase_access_token', 3500, function () {
            $credentials = json_decode(
                file_get_contents(config('firebase.credentials')),
                true
            );

            $now = time();

            $payload = [
                'iss'   => $credentials['client_email'],
                'scope' => 'https://www.googleapis.com/auth/firebase.messaging',
                'aud'   => 'https://oauth2.googleapis.com/token',
                'iat'   => $now,
                'exp'   => $now + 3600,
            ];

            $jwt = $this->encodeJwt($payload, $credentials['private_key']);

            $response = Http::asForm()->post(
                'https://oauth2.googleapis.com/token',
                [
                    'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                    'assertion'  => $jwt,
                ]
            );

            if (! $response->successful()) {
                throw new \RuntimeException('Firebase auth failed');
            }

            return $response->json('access_token');
        });
    }

    private function encodeJwt(array $payload, string $privateKey): string
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

    private function base64Url(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }
}
