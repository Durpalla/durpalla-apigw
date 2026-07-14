<?php

namespace App\Support;

use App\Models\Merchant;
use App\Models\MerchantStaff;
use Illuminate\Support\Facades\Auth;

class MerchantContext
{
    /**
     * Authenticated merchant owner or staff from either session or API guard.
     */
    public static function user(): Merchant|MerchantStaff|null
    {
        foreach (['merchant', 'merchant_api', 'merchant_staff', 'merchant_staff_api'] as $guard) {
            $user = Auth::guard($guard)->user();
            if ($user instanceof Merchant || $user instanceof MerchantStaff) {
                return $user;
            }
        }

        return null;
    }

    /**
     * Resolve merchants.id for the current merchant owner or staff member.
     */
    public static function currentMerchantId(): ?int
    {
        $user = self::user();

        if ($user instanceof Merchant) {
            return (int) $user->id;
        }

        if ($user instanceof MerchantStaff) {
            return $user->merchant_id ? (int) $user->merchant_id : null;
        }

        return null;
    }
}
