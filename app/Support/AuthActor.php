<?php

namespace App\Support;

use App\Models\Agent;
use App\Models\Booking;
use App\Models\Customer;
use App\Models\Merchant;
use App\Models\MerchantStaff;
use App\Models\Partner;
use Illuminate\Database\Eloquent\Model;

class AuthActor
{
    /**
     * Set polymorphic booked_by on a booking from any authenticatable actor model.
     */
    public static function setBookedBy(Booking $booking, Model $model): Booking
    {
        $booking->booked_by_type = $model->getMorphClass();
        $booking->booked_by_id = $model->getKey();

        return $booking;
    }

    /**
     * Ownership / discount bucket for cabin availability (durpalla vs merchant).
     */
    public static function ownershipType(?object $user): string
    {
        if ($user === null) {
            return 'durpalla';
        }

        if ($user instanceof Customer || $user instanceof Agent || $user instanceof Partner) {
            return 'durpalla';
        }

        if ($user instanceof Merchant || $user instanceof MerchantStaff) {
            return 'merchant';
        }

        if (isset($user->type) && in_array($user->type, ['customer', 'admin', 'agent'], true)) {
            return 'durpalla';
        }

        return isset($user->type) ? (string) $user->type : 'durpalla';
    }

    public static function isCustomerOrAgent(?object $user): bool
    {
        return $user instanceof Customer
            || $user instanceof Agent
            || (isset($user->type) && in_array($user->type, ['customer', 'agent'], true));
    }

    public static function isSupervisor(?object $user): bool
    {
        return ($user instanceof MerchantStaff && $user->isSupervisor())
            || (isset($user->type) && $user->type === 'supervisor');
    }

    public static function isMerchantSide(?object $user): bool
    {
        return $user instanceof Merchant
            || $user instanceof MerchantStaff
            || (isset($user->type) && $user->type === 'merchant');
    }
}
