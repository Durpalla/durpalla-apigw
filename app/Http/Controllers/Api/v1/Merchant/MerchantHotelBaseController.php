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

    protected const STAY_TYPES = ['hotel', 'resort', 'homestay'];

    protected function saasEntitlements(): SaasEntitlementService
    {
        return app(SaasEntitlementService::class);
    }

    /**
     * Enforce allowed service types for stay endpoints (hotel/resort/homestay).
     * Backward compatible: empty list means "not restricted".
     */
    protected function assertHotelAllowed(int $ownerId): void
    {
        $allowed = $this->merchantAllowedServiceTypes($ownerId);
        if ($allowed === []) {
            return;
        }
        if (count(array_intersect($allowed, self::STAY_TYPES)) === 0) {
            abort(403, 'Stay management is not enabled for this merchant.');
        }
    }

    /**
     * @return list<string>
     */
    protected function merchantAllowedServiceTypes(int $ownerId): array
    {
        $merchant = Merchant::query()->find($ownerId);
        $allowed = $merchant ? $merchant->allowed_service_types : null;
        if (! is_array($allowed) || count($allowed) === 0) {
            return [];
        }

        return array_values(array_unique(array_filter(array_map(
            static fn ($type) => strtolower(trim((string) $type)),
            $allowed
        ))));
    }

    /**
     * Stay kinds this merchant may create/manage. Empty allowed list = all stay kinds.
     *
     * @return list<string>
     */
    protected function allowedStayPropertyTypes(int $ownerId): array
    {
        $allowed = $this->merchantAllowedServiceTypes($ownerId);
        if ($allowed === []) {
            return self::STAY_TYPES;
        }

        return array_values(array_intersect(self::STAY_TYPES, $allowed));
    }

    protected function assertPropertyTypeAllowed(int $ownerId, string $propertyType): void
    {
        $propertyType = strtolower(trim($propertyType));
        if (! in_array($propertyType, self::STAY_TYPES, true)) {
            abort(422, 'Invalid property type.');
        }
        $allowed = $this->allowedStayPropertyTypes($ownerId);
        if (! in_array($propertyType, $allowed, true)) {
            abort(403, 'This property type is not enabled for this merchant.');
        }
    }
}
