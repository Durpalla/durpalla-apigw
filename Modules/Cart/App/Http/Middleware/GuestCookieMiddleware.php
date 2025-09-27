<?php

namespace Modules\Cart\App\Http\Middleware;

use App\Helpers\CommonHelper;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Modules\Cart\App\Models\Cart;

class GuestCookieMiddleware
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next)
    {
        $guestId = $request->cookie('guest_unique_id', $request->header('X-GUEST-ID'));

        if ($guestId && auth('customer')->check()) {
            Cart::where('token', decrypt($guestId))->update(['customer_id' => auth('customer')->id()]);
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
