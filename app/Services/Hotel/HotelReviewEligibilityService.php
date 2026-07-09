<?php

namespace App\Services\Hotel;

use App\Constants\AppConst;
use App\Models\Hotel;
use App\Models\HotelReservation;
use App\Models\HotelReview;
use App\Notifications\HotelReviewPrompt;
use Carbon\Carbon;
use Illuminate\Support\Facades\Schema;

class HotelReviewEligibilityService
{
    public function userHasSubmittedReview(int $userId, int $hotelId): bool
    {
        if (! Schema::hasColumn((new HotelReview)->getTable(), 'user_id')) {
            return false;
        }

        return HotelReview::query()
            ->where('hotel_id', $hotelId)
            ->where('user_id', $userId)
            ->exists();
    }

    public function userCanReviewHotel(int $userId, int $hotelId): bool
    {
        if ($this->userHasSubmittedReview($userId, $hotelId)) {
            return false;
        }

        return $this->findCompletedStay($userId, $hotelId) !== null;
    }

    public function findCompletedStay(int $userId, int $hotelId): ?HotelReservation
    {
        if (! Schema::hasTable('hotel_reservations')) {
            return null;
        }

        $today = Carbon::today();

        return HotelReservation::query()
            ->with('hotel')
            ->where('user_id', $userId)
            ->where('hotel_id', $hotelId)
            ->where('status', HotelReservation::STATUS_CONFIRMED)
            ->whereDate('check_out', '<=', $today)
            ->whereHas('booking', function ($q) {
                $q->where('status', AppConst::BOOKING_COMPLETE);
            })
            ->orderByDesc('check_out')
            ->first();
    }

    /**
     * Notify guests after checkout to leave a verified review (once per reservation).
     */
    public function dispatchCheckoutReviewPrompts(): int
    {
        if (! Schema::hasTable('hotel_reservations')) {
            return 0;
        }

        $windowDays = max(1, (int) config('hotel.review_prompt_window_days', 14));
        $fromDate = Carbon::today()->subDays($windowDays);

        $reservations = HotelReservation::query()
            ->with(['user', 'hotel'])
            ->where('status', HotelReservation::STATUS_CONFIRMED)
            ->whereDate('check_out', '<=', Carbon::today())
            ->whereDate('check_out', '>=', $fromDate)
            ->whereHas('booking', function ($q) {
                $q->where('status', AppConst::BOOKING_COMPLETE);
            })
            ->orderBy('check_out')
            ->get();

        $sent = 0;
        foreach ($reservations as $reservation) {
            $user = $reservation->user;
            $hotel = $reservation->hotel;
            if ($user === null || $hotel === null) {
                continue;
            }
            if ($this->userHasSubmittedReview((int) $user->id, (int) $hotel->id)) {
                continue;
            }
            if ($this->reviewPromptAlreadySent($user, (int) $reservation->id)) {
                continue;
            }

            $user->notify(new HotelReviewPrompt($reservation, $hotel));
            $sent++;
        }

        return $sent;
    }

    private function reviewPromptAlreadySent($user, int $reservationId): bool
    {
        return $user->notifications()
            ->where('type', HotelReviewPrompt::class)
            ->where('data->reservation_id', $reservationId)
            ->exists();
    }
}
