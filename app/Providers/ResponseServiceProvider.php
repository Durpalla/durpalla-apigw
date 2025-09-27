<?php

namespace App\Providers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Routing\ResponseFactory;
use Illuminate\Support\ServiceProvider;

class ResponseServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     *
     * @return void
     */
    public function register()
    {
        //
    }

    /**
     * Bootstrap services.
     *
     * @return void
     */
    public function boot(ResponseFactory $factory)
    {
        $factory->macro('success', function ($data = null, $message = 'Success!', $cookies = []) use ($factory) {
            if ($data instanceof Response || $data instanceof JsonResponse) {
                return $data; // already a response, just return
            }

            $format = [
                'status' => true,
                'message' => $message,
                'data' => $data,
            ];

            $response = $factory->make($format);

            if($cookies) {
                // Attach cookies if provided
                foreach ($cookies as $cookie) {
                    $response->headers->setCookie($cookie);
                }
            }

            return $response;
        });

        $factory->macro('error', function ($params = []) use ($factory){
            $format = [
                'status' => false,
                'message' => $params['message'] ?? 'Error!',
                'errors' => $params['errors'] ?? [],
            ];

            return $factory->make($format, $params['code'] ?? 500);
        });

        $factory->macro('failed', function ($params = []) use ($factory){
            $format = [
                'status' => false,
                'message' => $params['message'] ?? 'Failed!',
                'errors' => $params['errors'] ?? [],
            ];

            return $factory->make($format, $params['code'] ?? 500);
        });
    }
}
