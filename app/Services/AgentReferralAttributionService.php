<?php

namespace App\Services;

use App\Models\AgentReferredMerchant;
use App\Models\Booking;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AgentReferralAttributionService
{
    /**
     * Stamp bookings.referring_agent_id from the merchant who owns the inventory.
     * Runs for customer and agent bookings so referrers can earn referral commission
     * alongside (or instead of) the booking agent's direct commission.
     */
    public function attribute(Booking $booking): void
    {
        if ($booking->referring_agent_id) {
            return;
        }

        $referredAgentId = $this->resolveReferringAgentId($booking);
        if (! $referredAgentId) {
            return;
        }

        $booking->referring_agent_id = $referredAgentId;
        $booking->saveQuietly();
    }

    /**
     * Resolve referring agent without mutating the booking (admin verify / dry checks).
     */
    public function resolveReferringAgentId(Booking $booking): ?int
    {
        if ($booking->referring_agent_id) {
            return (int) $booking->referring_agent_id;
        }

        $merchantIds = [];
        if (Schema::hasTable('booking_items')) {
            $merchantIds = array_merge($merchantIds, DB::table('booking_items')
                ->join('vehicles', 'vehicles.id', '=', 'booking_items.vehicle_id')
                ->where('booking_items.booking_id', $booking->id)
                ->pluck('vehicles.merchant_id')->all());
            if (Schema::hasColumn('booking_items', 'hotel_id')) {
                $merchantIds = array_merge($merchantIds, DB::table('booking_items')
                    ->join('hotels', 'hotels.id', '=', 'booking_items.hotel_id')
                    ->where('booking_items.booking_id', $booking->id)
                    ->pluck('hotels.merchant_id')->all());
            }
        }
        foreach (['hotel_reservations', 'booking_hotel_items'] as $table) {
            if (Schema::hasTable($table)) {
                $merchantIds = array_merge($merchantIds, DB::table($table)
                    ->join('hotels', 'hotels.id', '=', "{$table}.hotel_id")
                    ->where("{$table}.booking_id", $booking->id)
                    ->pluck('hotels.merchant_id')->all());
            }
        }

        $merchantIds = array_values(array_unique(array_filter($merchantIds)));
        if ($merchantIds === []) {
            return null;
        }

        $referred = AgentReferredMerchant::query()
            ->where('status', AgentReferredMerchant::STATUS_LIVE)
            ->whereIn('merchant_id', $merchantIds)
            ->orderBy('id')
            ->first();

        if ($referred) {
            return (int) $referred->agent_id;
        }

        $referredAgentId = DB::table('merchants')
            ->whereIn('id', $merchantIds)
            ->whereNotNull('referring_agent_id')
            ->value('referring_agent_id');

        return $referredAgentId ? (int) $referredAgentId : null;
    }
}
