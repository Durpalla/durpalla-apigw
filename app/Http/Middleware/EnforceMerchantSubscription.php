<?php

namespace App\Http\Middleware;

use App\Models\Merchant;
use App\Models\MerchantStaff;
use Closure;
use Illuminate\Http\Request;
use App\Services\Saas\SaasEntitlementService;
use Symfony\Component\HttpFoundation\Response;

/**
 * Cuts merchant/counter/desk booking channels while a subscription is overdue
 * (OTA-only mode). Apply to merchant- and supervisor-initiated booking routes.
 * Durpalla OTA/customer booking routes are never wrapped with this middleware.
 */
class EnforceMerchantSubscription
{
    public function __construct(private readonly SaasEntitlementService $entitlements)
    {
    }

    public function handle(Request $request, Closure $next): Response
    {
        $merchantId = $this->resolveMerchantId($request);

        if ($merchantId !== null && $this->entitlements->isOtaOnly($merchantId)) {
            return response()->json([
                'success' => false,
                'message' => 'Your subscription is overdue. Counter and merchant bookings are paused until payment is made. Only Durpalla (OTA) bookings remain active.',
                'code' => 'subscription_overdue',
            ], 403);
        }

        return $next($request);
    }

    private function resolveMerchantId(Request $request): ?int
    {
        $user = $request->user();

        if ($user instanceof Merchant) {
            return (int) $user->id;
        }

        if ($user instanceof MerchantStaff && $user->merchant_id) {
            return (int) $user->merchant_id;
        }

        return null;
    }
}
