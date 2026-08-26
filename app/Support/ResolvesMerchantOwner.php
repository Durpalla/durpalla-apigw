<?php

namespace App\Support;

use App\Models\Merchant;
use App\Models\MerchantStaff;
use Illuminate\Http\Request;

trait ResolvesMerchantOwner
{
    /**
     * Resolve merchants.id for the authenticated merchant owner or staff member.
     */
    protected function merchantOwnerId(Request $request): int
    {
        $user = $request->user() ?? MerchantContext::user();

        if ($user instanceof Merchant) {
            return (int) $user->id;
        }

        if ($user instanceof MerchantStaff && $user->merchant_id) {
            return (int) $user->merchant_id;
        }

        $fromContext = MerchantContext::currentMerchantId();
        if ($fromContext) {
            return $fromContext;
        }

        abort(403, 'Merchant access required.');
    }

    protected function assertMainMerchant(Request $request): Merchant
    {
        $user = $request->user() ?? MerchantContext::user();
        if (! $user instanceof Merchant) {
            abort(403, 'Only the main merchant account can manage users and supervisors.');
        }

        return $user;
    }
}
