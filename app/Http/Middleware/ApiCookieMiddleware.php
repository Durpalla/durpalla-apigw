<?php

namespace App\Http\Middleware;

use App\Helpers\CommonHelper;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use App\Models\Cart;
use Symfony\Component\HttpFoundation\Response;

class ApiCookieMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param \Closure(Request): (Response) $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->is('up')) {
            return $next($request);
        }

        $guestId = $request->cookie('guest_unique_id', $request->header('X-GUEST-ID'));

        if ($guestId && auth('customer')->check()) {
            try {
                Cart::query()
                    ->useWritePdo()
                    ->where('token', decrypt($guestId))
                    ->update(['customer_id' => auth('customer')->id()]);
            } catch (\Throwable $e) {
                Log::warning('Cart guest merge skipped', ['message' => $e->getMessage()]);
            }
        }

        if (!$guestId) {
            $guestId = CommonHelper::generateUniqueUUID();
        }

        Log::info('guest_unique_id in request: ' . $guestId);

        $request->merge(['guest_unique_id' => $guestId]);

        $response = $next($request);

        $response->withCookie(
            cookie('guest_unique_id', $guestId, 60 * 24 * 30, '/', null, true, false, false, 'none')
        );

        return $response;
    }
}
