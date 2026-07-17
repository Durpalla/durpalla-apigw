<?php

namespace App\Services;

use App\Constants\AppConst;
use App\Models\Booking;
use App\Models\Party;
use App\Models\ScheduleCabinMapping;
use App\Models\VehicleSchedule;
use App\Models\WalletTransaction;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Cancels a reseller booking and refunds the reseller wallet according to the
 * merchant's tiered cancellation policy.
 *
 * Refund basis = what was debited from the fund (net of commission,
 * bookings.wallet_debit_amount). Refund = basis * refund_percent, where the
 * percentage comes from the merchant tiers by hours-before-departure (0% when
 * cancelled inside the lowest tier, e.g. < 6h). Inventory is released.
 */
class ResellerCancellationService
{
    public function __construct(
        private readonly ResellerWalletService $wallet,
        private readonly MerchantCancellationPolicyResolver $policy,
    ) {
    }

    /**
     * @return array{booking:Booking, refund_percent:float, refund_amount:float, wallet_debit_amount:float}
     */
    public function cancel(Party $partner, Booking $booking): array
    {
        if ((int) $booking->party_id !== (int) $partner->id) {
            throw new \RuntimeException('This booking does not belong to your account.');
        }

        if (in_array($booking->status, [AppConst::BOOKING_CANCELLED, AppConst::BOOKING_REJECTED], true)) {
            throw new \RuntimeException('This booking is already cancelled.');
        }

        return DB::transaction(function () use ($partner, $booking) {
            $booking = Booking::where('id', $booking->id)->lockForUpdate()->firstOrFail();
            $booking->load('bookingItems');

            [$departure, $merchantId] = $this->departureAndMerchant($booking);

            $basis = (float) ($booking->wallet_debit_amount ?? 0);
            $refundPercent = $this->policy->refundPercent($merchantId, $departure, 'transport');
            $refundAmount = round($basis * $refundPercent / 100, 2);

            // Release inventory.
            $mappingIds = $booking->bookingItems->pluck('mapping_id')->filter()->all();
            if (! empty($mappingIds)) {
                ScheduleCabinMapping::whereIn('id', $mappingIds)->update([
                    'booked' => 0,
                    'booking_id' => null,
                ]);
            }

            // Mark items + booking cancelled.
            $booking->bookingItems()->update(['status' => AppConst::BOOKING_ITEM_CANCELLED]);
            $booking->update(['status' => AppConst::BOOKING_CANCELLED]);

            // Refund the fund (idempotent per booking).
            if ($refundAmount > 0) {
                $this->wallet->credit(
                    (int) $partner->id,
                    $refundAmount,
                    WalletTransaction::SOURCE_REFUND,
                    'refund_'.$booking->id,
                    [
                        'booking_id' => $booking->id,
                        'refund_percent' => $refundPercent,
                        'basis' => $basis,
                    ],
                    'Refund for cancelled booking #'.$booking->id.' ('.$refundPercent.'%)',
                    $partner
                );
            }

            return [
                'booking' => $booking->fresh(['bookingItems']),
                'refund_percent' => $refundPercent,
                'refund_amount' => $refundAmount,
                'wallet_debit_amount' => $basis,
            ];
        }, 3);
    }

    /**
     * Earliest trip departure datetime and its merchant id (for policy lookup).
     *
     * @return array{0:Carbon,1:?int}
     */
    private function departureAndMerchant(Booking $booking): array
    {
        $tripIds = $booking->bookingItems->pluck('trip_id')->filter()->unique()->all();

        $schedule = VehicleSchedule::whereIn('id', $tripIds)
            ->orderBy('leaving_at')
            ->first();

        $departure = $schedule?->leaving_at
            ? Carbon::parse((string) $schedule->leaving_at)
            : Carbon::parse((string) ($booking->from_date ?? now()));

        $merchantId = $schedule?->merchant_id ? (int) $schedule->merchant_id : null;

        return [$departure, $merchantId];
    }
}
