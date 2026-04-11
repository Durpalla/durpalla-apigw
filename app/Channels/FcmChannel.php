<?php


namespace App\Channels;

use App\Gateways\Push\Firebase;
use App\Helpers\LogHelper;
use App\Models\Gateway;
use Exception;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class FcmChannel
{
    public function send($notifiable, Notification $notification): void
    {
        try {
            $params = $notification->toFcm($notifiable);
            $gateway = Gateway::find(2);
            if (!$gateway) {
                Log::warning('FCM: Gateway id 2 not found, push notification skipped.');
                return;
            }
            (new Firebase($gateway))->send($params);
        } catch (\Exception $exception) {
            LogHelper::exception($exception, ['keyword' => 'FCM_SEND_EXCEPTION']);
            Log::error($exception);
        }
    }

    private function sendViaHuaweiPush($fields, $notifiable)
    {
        try {
            $type = strtolower($notifiable->userType->name ?? 'sr');
            Log::debug("USER_TYPE: " . $type);
            $token = $this->fetchHuaweiBearerToken($type);

            Log::debug("HUAWEI_BEARER_TOKEN: " . $token);
            if ($token === null) {
                throw new Exception('Huawei Token Not found');
            }
            $url = 'https://push-api.cloud.huawei.com/v1/' . config('huawei.' . $type . '.app_id') . '/messages:send';
            $headers = array(
                'Authorization: Bearer ' . $token . '',
                'Content-Type: application/json; charset=UTF-8'
            );

            Log::debug('PUSH_HEADER: ' . json_encode($headers));

            $ch = curl_init();

            curl_setopt($ch, CURLOPT_URL, $url);

            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

            curl_setopt($ch, CURLOPT_POSTFIELDS, $this->buildHuaweiBody($fields));

            $result = curl_exec($ch);

            if ($result === FALSE) {
                Log::critical('Curl failed: ' . curl_error($ch));
            }

            curl_close($ch);
        } catch (Exception $e) {
            Log::error($e);
        }
    }

    private function fetchHuaweiBearerToken($type)
    {
        $str = null;
        try {
            $url = "https://oauth-login.cloud.huawei.com/oauth2/v3/token";
            $tokens = Cache::get('huawei_tokens');

            Log::debug("TOKEN_FROM_CACHE: " . json_encode($tokens));
            if ($tokens != null && array_key_exists($type, $tokens) && $tokens[$type]['expire_at'] > now()) {
                $str = $tokens[$type]['token'];
            } else {

                $data = [
                    'grant_type' => 'client_credentials',
                    'client_id' => config('huawei.' . $type . '.app_id'),
                    'client_secret' => config('huawei.' . $type . '.secret')
                ];

                $token = Http::asForm()->post($url, $data)->json();
                Log::debug(json_encode($token));

                if (is_array($token) && array_key_exists('access_token', $token) && $token['access_token']) {
                    Cache::put('huawei_tokens', [
                        $type => [
                            'token' => $token['access_token'],
                            'expire_at' => now()->addSeconds($token['expires_in'])
                        ]
                    ]);
                    $str = $token['access_token'];
                }
            }
        } catch (Exception $exception) {
            Log::error($exception);
        }

        return $str;
    }

    private function buildHuaweiBody($fields): string
    {
        return '{
            "validate_only": false,
            "message": {
                "data": "{\"title\":\"' . $fields['title'] . '\",\"subtitle\":\"' . $fields['message'] . '\",\"action_url\":\"' . $fields['action_url'] . '\"}",
                "token": ["' . $fields['token'] . '"]
            }
        }';
    }
}
