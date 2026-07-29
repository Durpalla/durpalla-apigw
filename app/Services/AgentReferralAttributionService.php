<?php

namespace App\Services;

use App\Models\Agent;
use App\Models\AgentReferredMerchant;
use App\Models\Booking;
use App\Models\Merchant;
use Illuminate\Support\Facades\DB;

class AgentReferralAttributionService
{
    /**
     * Attribute booking to the referring agent who referred the merchant that owns the inventory.
     *
     * Agent counter bookings (booked_by = Agent) earn seller commission, not referral credit.
     */
    public function attribute(Booking $booking): void
    {
        if ($booking->referring_agent_id) {
            return;
        }

        if ($booking->booked_by_type === Agent::class) {
            return;
        }

        $booking->loadMissing(['bookingItems.vehicle', 'bookingItems.hotel']);

        $merchantIds = [];
        foreach ($booking->bookingItems as $item) {
            if ($item->vehicle?->merchant_id) {
                $merchantIds[] = (int) $item->vehicle->merchant_id;
            }
            if ($item->hotel_id) {
                $hotelMerchantId = DB::table('hotels')->where('id', $item->hotel_id)->value('merchant_id');
                if ($hotelMerchantId) {
                    $merchantIds[] = (int) $hotelMerchantId;
                }
            }
        }

        $merchantIds = array_values(array_unique(array_filter($merchantIds)));
        if ($merchantIds === []) {
            return;
        }

        $referred = AgentReferredMerchant::query()
            ->where('status', AgentReferredMerchant::STATUS_LIVE)
            ->whereIn('merchant_id', $merchantIds)
            ->orderBy('id')
            ->first();

        if (! $referred) {
            $referredAgentId = Merchant::query()
                ->whereIn('id', $merchantIds)
                ->whereNotNull('referring_agent_id')
                ->value('referring_agent_id');
            if ($referredAgentId) {
                $booking->referring_agent_id = (int) $referredAgentId;
                $booking->saveQuietly();
            }

            return;
        }

        $booking->referring_agent_id = $referred->agent_id;
        $booking->saveQuietly();
    }
}
