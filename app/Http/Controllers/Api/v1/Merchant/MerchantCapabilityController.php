<?php

namespace App\Http\Controllers\Api\v1\Merchant;

use App\Http\Controllers\Controller;
use App\Models\Merchant;
use App\Support\ResolvesMerchantOwner;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use App\Services\Saas\SaasEntitlementService;

class MerchantCapabilityController extends Controller
{
    use ResolvesMerchantOwner;

    /**
     * GET /api/v1/merchant/capabilities
     *
     * Returns feature flags and allowed service types for this merchant.
     *
     * @return JsonResponse
     */
    public function show(Request $request): JsonResponse
    {
        $ownerId = $this->merchantOwnerId($request);

        $merchant = Merchant::query()->find($ownerId);
        $allowed = [];
        if ($merchant !== null) {
            try {
                $raw = $merchant->allowed_service_types;
                $allowed = is_array($raw) ? $raw : [];
            } catch (\Throwable) {
                $allowed = [];
            }
        }
        $allowed = array_values(array_unique(array_filter(array_map('strval', $allowed))));

        // Backward compatible: empty list means "no explicit restriction".
        $isRestricted = count($allowed) > 0;
        $canHotels = ! $isRestricted || in_array('hotel', $allowed, true);

        $transportTypes = $this->transportServiceTypes();
        $canTransport = ! $isRestricted;
        if ($isRestricted) {
            foreach ($allowed as $type) {
                $normalized = strtolower(trim((string) $type));
                if ($normalized === '' || $normalized === 'hotel') {
                    continue;
                }
                if (in_array($normalized, $transportTypes, true)) {
                    $canTransport = true;
                    break;
                }
                // Unknown non-hotel types still count as transport (e.g. vessel).
                $canTransport = true;
                break;
            }
        }

        $subscription = app(SaasEntitlementService::class)->capabilities($ownerId);

        return response()->json([
            'success' => true,
            'data' => [
                'merchant_owner_id' => (string) $ownerId,
                'allowed_service_types' => $allowed,
                'is_service_type_restricted' => $isRestricted,
                'can_manage_hotels' => $canHotels,
                'can_manage_transport' => $canTransport,
                'subscription' => $subscription,
            ],
        ]);
    }

    /**
     * @return list<string>
     */
    private function transportServiceTypes(): array
    {
        $types = array_map('strval', (array) config('transport.vehicle_types', ['bus', 'launch', 'train', 'air']));
        $aliases = array_map('strval', array_keys((array) config('transport.vehicle_type_alias', [])));
        $aliasTargets = array_map('strval', array_values((array) config('transport.vehicle_type_alias', [])));

        return array_values(array_unique(array_map(
            'strtolower',
            array_merge($types, $aliases, $aliasTargets, ['vessel', 'vessels', 'ship', 'boat'])
        )));
    }
}

