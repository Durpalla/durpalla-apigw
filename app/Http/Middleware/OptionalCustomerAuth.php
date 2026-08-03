<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Laravel\Sanctum\PersonalAccessToken;
use Symfony\Component\HttpFoundation\Response;

/**
 * Resolve Sanctum customer from Bearer token without requiring authentication.
 * Cart / lock routes stay public for guests but must see the customer after login.
 */
class OptionalCustomerAuth
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! Auth::guard('customer')->check() && $request->bearerToken()) {
            $accessToken = PersonalAccessToken::findToken($request->bearerToken());
            if ($accessToken?->tokenable) {
                Auth::guard('customer')->setUser($accessToken->tokenable);
            }
        }

        return $next($request);
    }
}
