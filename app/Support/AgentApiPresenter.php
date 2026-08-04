<?php

namespace App\Support;

use App\Models\Agent;
use App\Models\AgentCommission;
use App\Models\AgentIncentive;
use App\Models\AgentReferredMerchant;
use App\Models\AgentReferredMerchantDocument;
use App\Models\AgentReferredProperty;
use App\Models\Booking;
use App\Models\BookingItem;
use App\Models\Vehicle;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class AgentApiPresenter
{
    public static function user(Agent $user): array
    {
        $user->loadMissing('meta');
        $incentive = AgentIncentive::query()->where('agent_id', $user->id)->first();

        $status = (int) $user->status;

        return [
            'id' => $user->id,
            'name' => $user->name,
            'mobile' => $user->mobile,
            'email' => $user->email,
            'nidNo' => $user->meta?->nid_no,
            'city' => $user->meta?->city,
            'address' => $user->meta?->address,
            'profilePicUrl' => $user->profile_pic_url,
            'commissionRate' => $incentive ? (float) $incentive->incentive : 0,
            'status' => $status,
            'statusLabel' => match ($status) {
                1 => 'active',
                2 => 'inactive',
                default => 'pending',
            },
        ];
    }

    public static function commission(AgentCommission $commission): array
    {
        return [
            'id' => $commission->id,
            'propertyId' => null,
            'bookingItemId' => $commission->booking_item_id,
            'propertyName' => $commission->purpose,
            'bookingReference' => $commission->booking_item_id ? (string) $commission->booking_item_id : $commission->type,
            'bookingAmount' => (float) $commission->total_sale,
            'commissionAmount' => (float) $commission->amount,
            // Every row reaching this presenter is already a credited commission
            // (see AgentCommissionController) - it was added to the wallet balance
            // by commission:journey-complete once the trip settled.
            'status' => 'SETTLED',
            'commissionDate' => $commission->commission_date,
            'createdAt' => $commission->commission_date,
        ];
    }

    public static function booking(Booking $booking): array
    {
        $booking->loadMissing(['customer', 'bookingItems', 'bookingItems.vehicle', 'bookingItems.item', 'bookingItems.trip']);
        $firstItem = $booking->bookingItems->first();
        $routeOrStay = $firstItem?->route_name
            ?: ($booking->from_date && $booking->to_date
                ? trim(($booking->from_date ?? '').' → '.($booking->to_date ?? ''))
                : null);

        $propertyName = null;
        $propertyType = null;
        if ($firstItem?->hotel_id) {
            $propertyType = 'HOTEL';
            $propertyName = $firstItem->hotel?->name;
        } elseif ($firstItem?->vehicle_id) {
            $propertyType = 'BUS_COMPANY';
            $propertyName = $firstItem->vehicle?->name ?? $firstItem->route_name;
        }

        // Agent app bookings report source "agent" - "counter" is reserved for
        // merchant panel/app walk-in bookings.
        $source = 'referral';
        if ($booking->booked_by_type === Agent::class) {
            $source = 'agent';
        }

        $vehicle = $firstItem?->vehicle;
        $vehicleType = null;
        $isAc = null;
        if ($vehicle) {
            $vehicleType = strtoupper((string) $vehicle->vehicle_type) ?: null;
            $isAc = (bool) $vehicle->ac_available;
        } elseif ($firstItem?->hotel_id) {
            $vehicleType = 'HOTEL';
        }

        $items = $booking->bookingItems;
        $passengerCount = (int) $items->sum(function (BookingItem $item) {
            $passenger = $item->passenger;
            // Some legacy rows store the passenger JSON double-encoded, in which
            // case the model's array cast only unwraps the outer string layer.
            if (is_string($passenger)) {
                $decoded = json_decode($passenger, true);
                $passenger = is_array($decoded) ? $decoded : [];
            }
            if (! is_array($passenger)) {
                $passenger = [];
            }
            return max(1, (int) ($passenger['person'] ?? 1));
        });
        $seatLabels = $items->map(fn (BookingItem $item) => $item->item?->cabin_no)
            ->filter()
            ->values()
            ->all();
        $seatType = $firstItem?->booking_type ? strtolower((string) $firstItem->booking_type) : null;

        // Prefer the schedule's actual departure time over trip_date, which is
        // often stored as a bare date (midnight) with no time component.
        $tripDateTime = $firstItem?->trip?->leaving_at ?: ($firstItem?->trip_date ?: $booking->from_date);

        return [
            'id' => $booking->id,
            'reference' => (string) $booking->id,
            'bookingReference' => self::formatBookingReference($booking),
            'bookingDate' => $booking->booking_date,
            'tripDateTime' => $tripDateTime instanceof Carbon ? $tripDateTime->toDateTimeString() : $tripDateTime,
            'status' => $booking->status,
            'displayStatus' => self::displayStatus($booking, $source, $firstItem),
            'platform' => $booking->platform,
            'serviceType' => $booking->service_type ?? 'transport',
            'source' => $source,
            'propertyName' => $propertyName,
            'propertyType' => $propertyType,
            'vehicleType' => $vehicleType,
            'isAc' => $isAc,
            'seatType' => $seatType,
            'seatLabels' => $seatLabels,
            'passengerCount' => max(1, $passengerCount ?: $items->count()),
            'customerName' => $booking->customer?->name,
            'customerMobile' => $booking->customer?->mobile,
            'totalAmount' => (float) $booking->total_amount,
            'chargeTotal' => (float) $booking->charge_total,
            'vatTotal' => (float) $booking->vat_total,
            'totalDiscount' => (float) $booking->total_discount,
            'totalPayable' => (float) $booking->total_payable,
            'routeOrStay' => $routeOrStay,
            'cancellable' => self::isBookingCancellable($booking, $source, $firstItem),
        ];
    }

    /**
     * Human-friendly booking reference for display, e.g. "DPB-20260804-0015".
     */
    public static function formatBookingReference(Booking $booking): string
    {
        return BookingInvoice::formatReference($booking);
    }

    /**
     * Client-facing status used to color the list chip: PENDING (awaiting
     * confirmation), UPCOMING (confirmed, trip ahead), COMPLETED (confirmed,
     * trip already happened), or CANCELLED (cancelled/failed/declined).
     */
    public static function displayStatus(
        Booking $booking,
        ?string $source = null,
        ?BookingItem $firstItem = null,
    ): string {
        $firstItem ??= $booking->bookingItems->first();
        $status = strtoupper((string) $booking->status);

        if (in_array($status, ['CANCELLED', 'CANCELED', 'FAILED', 'DECLINED'], true)) {
            return 'CANCELLED';
        }

        $isConfirmed = in_array($status, ['COMPLETE', 'PAID'], true);
        $isFutureEvent = self::resolveIsFutureEvent($firstItem?->trip_date ?: $booking->from_date);

        if ($isConfirmed) {
            return $isFutureEvent ? 'UPCOMING' : 'COMPLETED';
        }

        return 'PENDING';
    }

    /**
     * Only confirmed agent-app bookings for an upcoming trip/check-in date can be
     * cancelled - pending, failed, cancelled, or already-departed bookings cannot.
     */
    public static function isBookingCancellable(
        Booking $booking,
        ?string $source = null,
        ?BookingItem $firstItem = null,
    ): bool {
        $source ??= $booking->booked_by_type === Agent::class ? 'agent' : 'referral';
        $firstItem ??= $booking->bookingItems->first();

        $isConfirmed = strtoupper((string) $booking->status) === 'COMPLETE';
        $isFutureEvent = self::resolveIsFutureEvent($firstItem?->trip_date ?: $booking->from_date);

        return $source === 'agent' && $isConfirmed && $isFutureEvent;
    }

    /**
     * Resolves whether a trip/check-in date (transport trip_date or hotel from_date)
     * is still upcoming, so only confirmed bookings for a future date remain cancellable.
     */
    private static function resolveIsFutureEvent(mixed $date): bool
    {
        if (! $date) {
            return false;
        }

        try {
            return Carbon::parse($date)->isFuture();
        } catch (\Exception) {
            return false;
        }
    }

    public static function referredProperty(AgentReferredProperty $property): array
    {
        return [
            'id' => $property->id,
            'name' => $property->name,
            'type' => $property->type,
            'contactPerson' => $property->contact_person,
            'contactMobile' => $property->contact_mobile,
            'address' => $property->address,
            'city' => $property->city,
            'tradeLicenseNo' => $property->trade_license_no,
            'notes' => $property->notes,
            'active' => (bool) $property->active,
            'status' => $property->status,
            'createdAt' => $property->created_at?->toIso8601String(),
        ];
    }

    public static function referredMerchant(AgentReferredMerchant $merchant, bool $detailed = false): array
    {
        $data = [
            'id' => $merchant->id,
            'name' => $merchant->name,
            'businessType' => $merchant->business_type,
            'contactPerson' => $merchant->contact_person,
            'contactMobile' => $merchant->contact_mobile,
            'address' => $merchant->address,
            'city' => $merchant->city,
            'tradeLicenseNo' => $merchant->trade_license_no,
            'notes' => $merchant->notes,
            'status' => $merchant->status,
            'rejectReason' => $merchant->reject_reason,
            'merchantId' => $merchant->merchant_id,
            'documentCount' => (int) ($merchant->documents_count ?? $merchant->documents()->count()),
            'createdAt' => $merchant->created_at?->toIso8601String(),
        ];

        if ($detailed) {
            $merchant->loadMissing('documents');
            $data['documents'] = $merchant->documents
                ->map(fn (AgentReferredMerchantDocument $doc) => self::referredMerchantDocument($doc))
                ->values()
                ->all();

            $vehicleCount = 0;
            $hotelCount = 0;
            if ($merchant->merchant_id) {
                $vehicleCount = Vehicle::query()->where('merchant_id', $merchant->merchant_id)->count();
                if (DB::getSchemaBuilder()->hasTable('hotels')) {
                    $hotelCount = (int) DB::table('hotels')->where('merchant_id', $merchant->merchant_id)->count();
                }
            }
            $data['inventory'] = [
                'vehicleCount' => $vehicleCount,
                'hotelCount' => $hotelCount,
                'propertyCount' => $vehicleCount + $hotelCount,
            ];
        }

        return $data;
    }

    public static function referredMerchantDocument(AgentReferredMerchantDocument $doc): array
    {
        return [
            'id' => $doc->id,
            'type' => $doc->type,
            'url' => $doc->url,
            'createdAt' => $doc->created_at?->toIso8601String(),
        ];
    }
}
