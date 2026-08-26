<?php

namespace App\Services\Saas;

use App\Constants\AppConst;
use App\Models\Merchant;
use Illuminate\Support\Facades\DB;
use App\Models\SaasPlan;
use App\Models\SaasSubscription;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Resolves plan entitlements and enforces subscription limits for merchants.
 *
 * - Hotel: property + room quantity limits per plan.
 * - Transport: commission on all bookings (no hard feature gate).
 * - Overdue (past_due/suspended): OTA-only mode — non-OTA booking channels are cut,
 *   and the merchant may keep at most `overdue_block_limit` reserved items.
 */
class SaasEntitlementService
{
    /** @var array<int, SaasSubscription|null> */
    private array $subscriptionCache = [];

    public function subscriptionFor(int $merchantId): ?SaasSubscription
    {
        if (array_key_exists($merchantId, $this->subscriptionCache)) {
            return $this->subscriptionCache[$merchantId];
        }

        $merchant = Merchant::query()->find($merchantId);
        $subscription = $merchant?->currentSubscription();

        return $this->subscriptionCache[$merchantId] = $subscription;
    }

    public function planFor(int $merchantId): ?SaasPlan
    {
        $subscription = $this->subscriptionFor($merchantId);

        return $subscription?->plan;
    }

    /**
     * A merchant with no subscription is treated as unrestricted (legacy behavior).
     */
    public function hasActiveSubscription(int $merchantId): bool
    {
        $subscription = $this->subscriptionFor($merchantId);

        return $subscription !== null && $subscription->isActive();
    }

    public function isOtaOnly(int $merchantId): bool
    {
        $subscription = $this->subscriptionFor($merchantId);

        return $subscription !== null && $subscription->isOtaOnly();
    }

    /**
     * A merchant is only operational while its account status is active (1).
     * Any other status (pending/deactivated) or a soft-deleted record means a
     * total shutdown: no logins, no bookings, and hidden from all listings.
     */
    public function isMerchantActive(int $merchantId): bool
    {
        $merchant = Merchant::query()->find($merchantId);

        return $merchant !== null && (int) $merchant->status === AppConst::USER_ACTIVE;
    }

    /**
     * Hard gate used by every booking write path. Throws 403 when the owning
     * merchant is not active so no booking can be created at any level.
     */
    public function assertMerchantActive(int $merchantId): void
    {
        if (! $this->isMerchantActive($merchantId)) {
            $this->deny('This merchant account is currently inactive. Bookings and operations are unavailable.');
        }
    }

    public function commissionRateFor(int $merchantId): ?float
    {
        $subscription = $this->subscriptionFor($merchantId);
        if ($subscription === null) {
            return null;
        }

        return (float) $subscription->commission_rate;
    }

    public function blockLimitFor(int $merchantId): ?int
    {
        $plan = $this->planFor($merchantId);
        if ($plan === null) {
            return null;
        }

        return (int) $plan->overdue_block_limit;
    }

    public function countHotelProperties(int $merchantId): int
    {
        return (int) DB::table('hotels')
            ->where('merchant_id', $merchantId)
            ->whereNull('deleted_at')
            ->count();
    }

    public function countHotelRooms(int $merchantId): int
    {
        return (int) DB::table('hotel_rooms')
            ->join('hotels', 'hotels.id', '=', 'hotel_rooms.hotel_id')
            ->where('hotels.merchant_id', $merchantId)
            ->whereNull('hotels.deleted_at')
            ->count();
    }

    public function countActiveTransportBlocks(int $merchantId): int
    {
        return (int) DB::table('schedule_cabin_mappings')
            ->join('vehicle_schedules', 'vehicle_schedules.id', '=', 'schedule_cabin_mappings.schedule_id')
            ->where('vehicle_schedules.merchant_id', $merchantId)
            ->where('schedule_cabin_mappings.is_reserved', 1)
            ->count();
    }

    public function assertCanCreateHotelProperty(int $merchantId): void
    {
        $plan = $this->planFor($merchantId);
        if ($plan === null || $plan->hasUnlimitedProperties()) {
            return;
        }

        if ($this->countHotelProperties($merchantId) >= (int) $plan->max_hotel_properties) {
            $this->deny(sprintf(
                'Your %s plan allows up to %d hotel properties. Upgrade your plan to add more.',
                $plan->name,
                (int) $plan->max_hotel_properties
            ));
        }
    }

    public function assertCanCreateHotelRooms(int $merchantId, int $adding = 1): void
    {
        $plan = $this->planFor($merchantId);
        if ($plan === null || $plan->hasUnlimitedRooms()) {
            return;
        }

        $adding = max(1, $adding);
        if (($this->countHotelRooms($merchantId) + $adding) > (int) $plan->max_hotel_rooms) {
            $this->deny(sprintf(
                'Your %s plan allows up to %d rooms in total. Upgrade your plan to add more.',
                $plan->name,
                (int) $plan->max_hotel_rooms
            ));
        }
    }

    /**
     * OTA-only mode cuts merchant/counter/desk booking channels.
     */
    public function assertBookingChannelAllowed(int $merchantId, string $bookingParty): void
    {
        if (! $this->isOtaOnly($merchantId)) {
            return;
        }

        if (strtolower($bookingParty) !== AppConst::PARTY_DURPALLA) {
            $this->deny('Your subscription is overdue. Counter and merchant bookings are paused until payment is made. Only Durpalla (OTA) bookings remain active.');
        }
    }

    /**
     * While in OTA-only mode, cap how many items a merchant may keep blocked/reserved.
     */
    public function assertCanReserveBlock(int $merchantId): void
    {
        if (! $this->isOtaOnly($merchantId)) {
            return;
        }

        $limit = $this->blockLimitFor($merchantId);
        if ($limit === null) {
            return;
        }

        if ($this->countActiveTransportBlocks($merchantId) >= $limit) {
            $this->deny(sprintf(
                'Your subscription is overdue. You can keep at most %d items blocked; release one or pay your invoice to block more. All other seats stay open for Durpalla customers.',
                $limit
            ));
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function capabilities(int $merchantId): array
    {
        $subscription = $this->subscriptionFor($merchantId);
        $plan = $subscription?->plan;

        $otaOnly = $subscription !== null && $subscription->isOtaOnly();

        return [
            'has_subscription' => $subscription !== null,
            'subscription_status' => $subscription?->status,
            'plan_code' => $plan?->code,
            'plan_name' => $plan?->name,
            'commission_rate' => $subscription ? (float) $subscription->commission_rate : null,
            'max_hotel_properties' => $plan?->max_hotel_properties,
            'max_hotel_rooms' => $plan?->max_hotel_rooms,
            'properties_used' => $this->countHotelProperties($merchantId),
            'rooms_used' => $this->countHotelRooms($merchantId),
            'ota_only_mode' => $otaOnly,
            'block_limit' => $plan?->overdue_block_limit,
            'blocks_used' => $this->countActiveTransportBlocks($merchantId),
        ];
    }

    private function deny(string $message): void
    {
        throw new HttpException(403, $message);
    }
}
