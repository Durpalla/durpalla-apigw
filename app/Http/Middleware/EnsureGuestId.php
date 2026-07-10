<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * Ensures every API request has a guest unique ID for cart / cabin locks.
 * Backend generates the ID, stores it encrypted in a cookie; frontend
 * stores the cookie and sends it with every request so guest users are
 * identified by this ID.
 */
class EnsureGuestId
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->is('up')) {
            return $next($request);
        }

        $cookieName = config('guest.cookie.name', 'guest_unique_id');
        $headerName = config('guest.header_name', 'X-Guest-Id');

        $encrypted = $request->cookie($cookieName) ?: $request->header($headerName);

        if ($encrypted) {
            try {
                decrypt($encrypted);
                $request->merge(['guest_unique_id' => $encrypted]);
                $response = $next($request);
                $this->attachCookie($response, $encrypted);
                $response->headers->set($headerName, $encrypted);
                return $response;
            } catch (\Throwable $e) {
                // Invalid or tampered value; generate new. Log at debug so PHPUnit does not mark tests as risky.
                \Illuminate\Support\Facades\Log::debug('Invalid or tampered guest id cookie', ['message' => $e->getMessage()]);
            }
        }

        $plain = (string) Str::uuid();
        $encrypted = encrypt($plain);
        $request->merge(['guest_unique_id' => $encrypted]);

        $response = $next($request);
        $this->attachCookie($response, $encrypted);
        $response->headers->set($headerName, $encrypted);
        return $response;
    }

    private function attachCookie(Response $response, string $encrypted): void
    {
        if (! $response instanceof \Illuminate\Http\Response) {
            return;
        }

        $minutes = config('guest.cookie.minutes', 60 * 24 * 30);
        $sameSite = config('guest.cookie.same_site', 'lax');

        $response->cookie(
            config('guest.cookie.name', 'guest_unique_id'),
            $encrypted,
            $minutes,
            config('guest.cookie.path', '/'),
            config('guest.cookie.domain'),
            (bool) config('guest.cookie.secure', true),
            (bool) config('guest.cookie.http_only', true),
            false,
            $sameSite
        );
    }
}
