<?php

namespace App\Support;

use App\Models\Agent;
use App\Models\AgentCommission;
use App\Models\AgentIncentive;
use App\Models\AgentReferredMerchant;
use App\Models\AgentReferredMerchantDocument;
use App\Models\AgentReferredProperty;
use App\Models\Booking;
use App\Models\Vehicle;
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
            'propertyName' => $commission->purpose,
            'bookingReference' => $commission->type,
            'bookingAmount' => (float) $commission->total_sale,
            'commissionAmount' => (float) $commission->amount,
            'status' => 'credited',
            'commissionDate' => $commission->commission_date,
            'createdAt' => $commission->commission_date,
        ];
    }

    public static function booking(Booking $booking): array
    {
        $booking->loadMissing(['customer', 'bookingItems']);
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

        $source = 'referral';
        if ($booking->booked_by_type === Agent::class) {
            $source = 'counter';
        }

        return [
            'id' => $booking->id,
            'reference' => (string) $booking->id,
            'bookingDate' => $booking->booking_date,
            'status' => $booking->status,
            'platform' => $booking->platform,
            'serviceType' => $booking->service_type ?? 'transport',
            'source' => $source,
            'propertyName' => $propertyName,
            'propertyType' => $propertyType,
            'customerName' => $booking->customer?->name,
            'customerMobile' => $booking->customer?->mobile,
            'totalAmount' => (float) $booking->total_amount,
            'chargeTotal' => (float) $booking->charge_total,
            'vatTotal' => (float) $booking->vat_total,
            'totalDiscount' => (float) $booking->total_discount,
            'totalPayable' => (float) $booking->total_payable,
            'routeOrStay' => $routeOrStay,
            'cancellable' => $source === 'counter'
                && ! str_contains(strtolower((string) $booking->status), 'cancel'),
        ];
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
