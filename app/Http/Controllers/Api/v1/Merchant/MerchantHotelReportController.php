<?php

namespace App\Http\Controllers\Api\v1\Merchant;

use App\Models\Booking;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\Hotel;

class MerchantHotelReportController extends MerchantHotelBaseController
{
    public function summary(Request $request): JsonResponse
    {
        $ownerId = $this->merchantOwnerId($request);
        $this->assertHotelAllowed($ownerId);

        $request->validate([
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d'],
        ]);

        $hotelsCount = Hotel::query()->where('merchant_id', $ownerId)->count();

        $bookingsQ = Booking::query()
            ->hotel()
            ->whereHas('hotelItems.hotel', function ($hq) use ($ownerId) {
                $hq->where('merchant_id', $ownerId);
            });

        if ($request->filled('from')) {
            $bookingsQ->whereDate('booking_date', '>=', $request->from);
        }
        if ($request->filled('to')) {
            $bookingsQ->whereDate('booking_date', '<=', $request->to);
        }

        $bookingsCount = (clone $bookingsQ)->count();
        $totalPayable = (float) (clone $bookingsQ)->sum('total_payable');
        $confirmedCount = (clone $bookingsQ)->where('status', 'confirmed')->count();
        $cancelledCount = (clone $bookingsQ)->where('status', 'cancelled')->count();

        return response()->json([
            'success' => true,
            'data' => [
                'hotels_count' => $hotelsCount,
                'bookings_count' => $bookingsCount,
                'confirmed_count' => $confirmedCount,
                'cancelled_count' => $cancelledCount,
                'total_payable_sum' => round($totalPayable, 2),
            ],
        ]);
    }

    /**
     * Lightweight export: returns rows as JSON (CSV export can be added later).
     */
    public function export(Request $request): JsonResponse
    {
        $ownerId = $this->merchantOwnerId($request);
        $this->assertHotelAllowed($ownerId);

        $request->validate([
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:5000'],
        ]);

        $q = Booking::query()
            ->hotel()
            ->with(['customer', 'hotelItems.hotel'])
            ->whereHas('hotelItems.hotel', function ($hq) use ($ownerId) {
                $hq->where('merchant_id', $ownerId);
            })
            ->orderByDesc('id');

        if ($request->filled('from')) {
            $q->whereDate('booking_date', '>=', $request->from);
        }
        if ($request->filled('to')) {
            $q->whereDate('booking_date', '<=', $request->to);
        }

        $limit = (int) ($request->get('limit', 1000));
        $rows = $q->limit($limit)->get()->map(function (Booking $b) {
            $firstHotel = null;
            if ($b->relationLoaded('hotelItems') && $b->hotelItems->first() && $b->hotelItems->first()->hotel) {
                $firstHotel = $b->hotelItems->first()->hotel->name;
            }
            return [
                'booking_id' => (int) $b->id,
                'booking_date' => (string) ($b->booking_date ?? ''),
                'status' => (string) ($b->status ?? ''),
                'customer_name' => $b->customer ? (string) ($b->customer->name ?? '') : '',
                'hotel' => $firstHotel,
                'from_date' => (string) ($b->from_date ?? ''),
                'to_date' => (string) ($b->to_date ?? ''),
                'total_payable' => (float) ($b->total_payable ?? 0),
            ];
        })->values();

        return response()->json(['success' => true, 'data' => $rows]);
    }
}

