<?php

namespace App\Http\Controllers\Api\v1\Merchant;

use App\Http\Controllers\Controller;
use App\Models\Merchant;
use App\Support\ResolvesMerchantOwner;

abstract class MerchantBoatBaseController extends Controller
{
    use ResolvesMerchantOwner;

    protected function assertBoatRentalAllowed(int $ownerId): void
    {
        $allowed = $this->merchantAllowedServiceTypes($ownerId);
        if ($allowed === []) {
            return;
        }
        if (! in_array('boat_rental', $allowed, true)) {
            abort(403, 'Boat rental management is not enabled for this merchant.');
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
}
