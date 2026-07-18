<?php

use App\Http\Middleware\JsonResponse;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        channels: __DIR__.'/../routes/channels.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Cloudflare / nginx terminate TLS — honor X-Forwarded-* for HTTPS cookies and HSTS.
        $middleware->trustProxies(
            at: '*',
            headers: \Illuminate\Http\Request::HEADER_X_FORWARDED_FOR
                | \Illuminate\Http\Request::HEADER_X_FORWARDED_HOST
                | \Illuminate\Http\Request::HEADER_X_FORWARDED_PORT
                | \Illuminate\Http\Request::HEADER_X_FORWARDED_PROTO
        );

        $middleware->use([
            \Illuminate\Http\Middleware\HandleCors::class,
            \App\Http\Middleware\SecurityHeaders::class,
        ]);

        $middleware->validateCsrfTokens(except: [
            'ipn/*',
            'api/payment/*/ipn',
        ]);

        $middleware->api(append: [
            \App\Http\Middleware\EnsureGuestId::class,
        ]);

        $middleware->alias([
            'JsonResponse' => JsonResponse::class,
            'guest.id' => \App\Http\Middleware\EnsureGuestId::class,
            'user.type' => \App\Http\Middleware\EnsureUserType::class,
            'client' => \Laravel\Passport\Http\Middleware\EnsureClientIsResourceOwner::class,
            'resolve.api.partner' => \App\Http\Middleware\ResolveApiPartner::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->render(function (\Illuminate\Auth\AuthenticationException $e, $request) {
            if ($request->expectsJson() || $request->is('api/*')) {
                $message = str_contains($request->path(), 'customer')
                    ? 'Unauthenticated. Get a token from POST /api/v1/customer/auth/login or /api/v1/customer/auth/register, then send it as: Authorization: Bearer <token>'
                    : 'Unauthenticated.';
                return response()->json([
                    'success' => false,
                    'message' => $message,
                ], 401);
            }
        });
    })->create();
