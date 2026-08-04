<?php

namespace App\Support;

use App\Constants\AppConst;
use App\Models\Agent;
use App\Models\AgentCommission;
use App\Models\AgentIncentive;
use App\Models\AgentReferredMerchant;
use App\Models\AgentReferredMerchantDocument;
use App\Models\AgentReferredProperty;
use App\Models\Booking;
use App\Models\BookingItem;
use App\Models\Vehicle;
use App\Services\PendingBookingPaymentWindow;
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
        $item = $commission->relationLoaded('bookingItem')
            ? $commission->bookingItem
            : $commission->bookingItem()->with(['booking', 'vehicle'])->first();

        $booking = $item?->booking;
        $routeOrVehicle = $item?->route_name
            ?: ($item?->vehicle?->name)
            ?: 'Commission';

        return [
            'id' => $commission->id,
            'propertyId' => null,
            'bookingItemId' => $commission->booking_item_id,
            'propertyName' => $routeOrVehicle,
            'bookingReference' => $booking?->pnr
                ?: $booking?->booking_code
                ?: ($commission->booking_item_id ? '#'.$commission->booking_item_id : (string) $commission->type),
            'bookingAmount' => (float) $commission->total_sale,
            'commissionAmount' => (float) $commission->amount,
            // Settled rows are already credited to the wallet by commission:journey-complete.
            'status' => 'SETTLED',
            'commissionDate' => $commission->commission_date,
            'createdAt' => $commission->commission_date,
        ];
    }

    /**
     * Virtual row for expected commission before journey settlement.
     */
    public static function pendingCommission(BookingItem $item, float $amount): array
    {
        $item->loadMissing(['booking', 'vehicle']);
        $booking = $item->booking;
        $routeOrVehicle = $item->route_name
            ?: ($item->vehicle?->name)
            ?: 'Commission';

        return [
            'id' => -1 * (int) $item->id,
            'propertyId' => null,
            'bookingItemId' => $item->id,
            'propertyName' => $routeOrVehicle,
            'bookingReference' => $booking?->pnr
                ?: $booking?->booking_code
                ?: '#'.$item->id,
            'bookingAmount' => max(0, (float) $item->price - (float) $item->discount),
            'commissionAmount' => $amount,
            'status' => 'PENDING',
            'commissionDate' => self::commissionDateString($item->booking_date)
                ?: self::commissionDateString($booking?->booking_date)
                ?: now()->toDateString(),
            'createdAt' => optional($booking?->created_at)?->toDateString()
                ?: now()->toDateString(),
        ];
    }

    private static function commissionDateString(mixed $value): ?string
    {
        if ($value instanceof \DateTimeInterface) {
            return $value->format('Y-m-d');
        }
        if (is_string($value) && trim($value) !== '') {
            return substr(trim($value), 0, 10);
        }

        return null;
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
            'cancellable' => self::hasCancellableItems($booking, $source, $firstItem),
            'pendingPaymentCancellable' => self::isPendingPaymentCancellable($booking),
            'paymentDueAt' => ($pw = PendingBookingPaymentWindow::paymentWindowPayload($booking))['payment_due_at'],
            'paymentDueAtMs' => $pw['payment_due_at_ms'],
            'canPay' => $pw['can_pay'],
            'gatewayId' => $pw['gateway_id'],
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
     * Confirmed upcoming agent bookings (item cancel request) OR unpaid PENDING
     * agent bookings (immediate void before/after payment window).
     */
    public static function isBookingCancellable(
        Booking $booking,
        ?string $source = null,
        ?BookingItem $firstItem = null,
    ): bool {
        $source ??= $booking->booked_by_type === Agent::class ? 'agent' : 'referral';
        if ($source !== 'agent') {
            return false;
        }

        if (self::isPendingPaymentCancellable($booking)) {
            return true;
        }

        $firstItem ??= $booking->bookingItems->first();
        $isConfirmed = strtoupper((string) $booking->status) === 'COMPLETE';
        $isFutureEvent = self::resolveIsFutureEvent($firstItem?->trip_date ?: $booking->from_date);

        return $isConfirmed && $isFutureEvent;
    }

    /** Unpaid PENDING agent counter booking — agent may void it entirely. */
    public static function isPendingPaymentCancellable(Booking $booking): bool
    {
        if ($booking->booked_by_type !== Agent::class) {
            return false;
        }

        return strtoupper((string) $booking->status) === strtoupper((string) AppConst::BOOKING_PENDING);
    }

    /**
     * True when the booking is eligible and at least one active item is not already
     * cancelled or covered by a pending cancellation request.
     * PENDING unpaid bookings are cancellable as a whole (no per-item request).
     */
    public static function hasCancellableItems(
        Booking $booking,
        ?string $source = null,
        ?BookingItem $firstItem = null,
    ): bool {
        if (self::isPendingPaymentCancellable($booking)) {
            return true;
        }

        if (! self::isBookingCancellable($booking, $source, $firstItem)) {
            return false;
        }

        $requestedIds = [];
        foreach ($booking->cancellations ?? [] as $cancellation) {
            foreach (explode(',', (string) ($cancellation->items ?? '')) as $id) {
                $id = (int) trim($id);
                if ($id > 0) {
                    $requestedIds[] = $id;
                }
            }
        }
        $requestedIds = array_values(array_unique($requestedIds));

        foreach ($booking->bookingItems as $item) {
            if ((int) $item->status !== AppConst::BOOKING_ITEM_ACTIVE) {
                continue;
            }
            if (in_array((int) $item->id, $requestedIds, true)) {
                continue;
            }

            return true;
        }

        return false;
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
        $types = self::parseBusinessTypes($merchant->business_type);
        $data = [
            'id' => $merchant->id,
            'name' => $merchant->name,
            'businessType' => $merchant->business_type,
            'businessTypes' => $types,
            'type' => $merchant->business_type,
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
            'logoUrl' => self::resolveLogoUrl($merchant),
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

    private static function resolveLogoUrl(AgentReferredMerchant $merchant): ?string
    {
        if ($merchant->relationLoaded('documents')) {
            $logo = $merchant->documents
                ->where('type', 'logo')
                ->sortByDesc('id')
                ->first();

            return $logo?->url;
        }

        $logo = $merchant->documents()
            ->where('type', 'logo')
            ->orderByDesc('id')
            ->first();

        return $logo?->url;
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

    /**
     * @return list<string>
     */
    private static function parseBusinessTypes(?string $raw): array
    {
        if ($raw === null || trim($raw) === '') {
            return [];
        }

        $parts = preg_split('/\s*,\s*/', trim($raw)) ?: [];
        $out = [];
        foreach ($parts as $part) {
            $code = match (strtolower(trim($part))) {
                'hotel', 'hotel_ops' => 'hotel',
                'bus', 'bus_company', 'bus_ops' => 'bus',
                'train' => 'train',
                'air', 'airline', 'flight' => 'air',
                'launch' => 'launch',
                'mixed', 'contract_middleman', 'partner' => 'mixed',
                default => strtolower(trim($part)),
            };
            if ($code !== '' && ! in_array($code, $out, true)) {
                $out[] = $code;
            }
        }

        return $out;
    }
}
