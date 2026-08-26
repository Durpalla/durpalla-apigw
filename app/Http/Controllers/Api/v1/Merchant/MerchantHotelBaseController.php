<?php

namespace App\Http\Controllers\Api\v1\Merchant;

use App\Http\Controllers\Controller;
use App\Models\Merchant;
use App\Support\ResolvesMerchantOwner;
use Illuminate\Http\Request;
use App\Services\Saas\SaasEntitlementService;

abstract class MerchantHotelBaseController extends Controller
{
    use ResolvesMerchantOwner;

    protected function saasEntitlements(): SaasEntitlementService
    {
        return app(SaasEntitlementService::class);
    }

    /**
     * Enforce allowed service types for hotel endpoints.
     * Backward compatible: empty list means "not restricted".
     */
    protected function assertHotelAllowed(int $ownerId): void
    {
        $merchant = Merchant::query()->find($ownerId);
        $allowed = $merchant ? $merchant->allowed_service_types : null;
        if (! is_array($allowed) || count($allowed) === 0) {
            return;
        }
        $allowed = array_values(array_unique(array_filter(array_map('strval', $allowed))));
        if (! in_array('hotel', $allowed, true)) {
            abort(403, 'Hotel management is not enabled for this merchant.');
        }
    }
}
