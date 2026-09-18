<?php

namespace App\Services\Entertainment;

use App\Constants\AppConst;
use App\Models\Booking;
use App\Models\BookingEntertainmentItem;
use App\Models\EntertainmentReview;
use Illuminate\Support\Facades\Schema;

class EntertainmentReviewEligibilityService
{
    public function userHasSubmittedReview(int $userId, int $venueId): bool
    {
        if (! Schema::hasTable('entertainment_reviews')) {
            return false;
        }

        return EntertainmentReview::query()
            ->where('venue_id', $venueId)
            ->where('user_id', $userId)
            ->exists();
    }

    public function userCanReviewVenue(int $userId, int $venueId): bool
    {
        if ($this->userHasSubmittedReview($userId, $venueId)) {
            return false;
        }

        return $this->findPaidBookingId($userId, $venueId) !== null;
    }

    public function findPaidBookingId(int $userId, int $venueId): ?int
    {
        if (! Schema::hasTable('booking_entertainment_items')) {
            return null;
        }

        $item = BookingEntertainmentItem::query()
            ->where('venue_id', $venueId)
            ->whereHas('booking', function ($q) use ($userId) {
                $q->where('service_type', 'entertainment')
                    ->where(function ($inner) use ($userId) {
                        $inner->where('customer_id', $userId)->orWhere('user_id', $userId);
                    })
                    ->where('status', AppConst::BOOKING_COMPLETE);
            })
            ->orderByDesc('id')
            ->first();

        return $item?->booking_id ? (int) $item->booking_id : null;
    }
}
