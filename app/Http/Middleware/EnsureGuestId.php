<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * Ensures every API request has a guest unique ID for cart / cabin locks.
 * Backend generates the ID, stores it encrypted in a Secure + HttpOnly cookie;
 * clients may also echo X-Guest-Id for non-browser callers.
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
                $this->attachGuestIdentity($response, $encrypted);

                return $response;
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::debug('Invalid or tampered guest id cookie', [
                    'message' => $e->getMessage(),
                ]);
            }
        }

        $plain = (string) Str::uuid();
        $encrypted = encrypt($plain);
        $request->merge(['guest_unique_id' => $encrypted]);

        $response = $next($request);
        $this->attachGuestIdentity($response, $encrypted);

        return $response;
    }

    private function attachGuestIdentity(Response $response, string $encrypted): void
    {
        $headerName = config('guest.header_name', 'X-Guest-Id');
        $response->headers->set($headerName, $encrypted);

        $minutes = (int) config('guest.cookie.minutes', 60 * 24 * 30);
        $sameSite = strtolower((string) config('guest.cookie.same_site', 'none'));
        if (! in_array($sameSite, ['lax', 'strict', 'none'], true)) {
            $sameSite = 'none';
        }

        // SameSite=None requires Secure; force it so browsers do not reject the cookie.
        $secure = (bool) config('guest.cookie.secure', true) || $sameSite === 'none';

        $domain = config('guest.cookie.domain');
        if (is_string($domain) && $domain !== '' && ! str_starts_with($domain, '.')) {
            // Parent-domain cookies should be ".durpalla.com", not "durpalla.com".
            $domain = '.' . ltrim($domain, '.');
        }

        $cookie = cookie(
            config('guest.cookie.name', 'guest_unique_id'),
            $encrypted,
            $minutes,
            config('guest.cookie.path', '/'),
            $domain ?: null,
            $secure,
            (bool) config('guest.cookie.http_only', true),
            false,
            $sameSite
        );

        $response->headers->setCookie($cookie);
    }
}
