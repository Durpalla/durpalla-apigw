<?php

namespace App\Http\Middleware;

use App\Constants\AppConst;
use App\Models\Merchant;
use App\Support\MerchantContext;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Total shutdown gate for inactive merchants.
 *
 * If the current request is authenticated as a merchant owner or any merchant
 * staff (supervisor/manager/etc.) whose owning merchant account is not active
 * (status !== 1, or soft-deleted), every request is rejected and the merchant
 * guards are logged out. This blocks existing web sessions and API tokens, not
 * just fresh logins.
 *
 * Admins/officers/support (web guard) are never gated — even when they are also
 * shadow-logged into a merchant — so back-office staff keep full access.
 */
class EnsureMerchantActive
{
    public function handle(Request $request, Closure $next): Response
    {
        // Back-office users on the web guard are exempt (also covers admins who
        // shadow-login into a merchant). Never gate or log these users out.
        $webUser = Auth::guard('web')->user();
        if ($webUser !== null
            && method_exists($webUser, 'hasAnyRole')
            && $webUser->hasAnyRole(['admin', 'officer', 'support'])) {
            return $next($request);
        }

        $merchantId = MerchantContext::currentMerchantId();

        // Not a merchant/staff request (customer, guest) — nothing to gate.
        if ($merchantId === null) {
            return $next($request);
        }

        $merchant = Merchant::query()->find($merchantId);

        if ($merchant !== null && (int) $merchant->status === AppConst::USER_ACTIVE) {
            return $next($request);
        }

        $this->logoutMerchantGuards();

        $message = __('This account is inactive. Please contact support.');

        if ($request->expectsJson() || $request->is('api/*')) {
            return response()->json([
                'success' => false,
                'message' => $message,
            ], 403);
        }

        return redirect()->route('login')->withErrors(['email' => $message]);
    }

    /**
     * Log out only the merchant/staff guards. We deliberately do NOT invalidate
     * or regenerate the whole session, because the same session can hold an
     * admin (web) login too — nuking it would sign the admin out as well.
     */
    private function logoutMerchantGuards(): void
    {
        foreach (['merchant', 'merchant_staff'] as $guard) {
            if (Auth::guard($guard)->check()) {
                Auth::guard($guard)->logout();
            }
        }

        // Revoke the current API token, if any (sanctum guards).
        foreach (['merchant_api', 'merchant_staff_api'] as $guard) {
            $user = Auth::guard($guard)->user();
            if ($user !== null && method_exists($user, 'currentAccessToken')) {
                $token = $user->currentAccessToken();
                if ($token !== null && method_exists($token, 'delete')) {
                    $token->delete();
                }
            }
        }
    }
}
