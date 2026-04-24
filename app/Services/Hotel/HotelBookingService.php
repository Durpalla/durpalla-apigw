<?php

namespace App\Services\Hotel;

use App\Constants\AppConst;
use App\Models\Booking;
use App\Models\Hotel;
use App\Models\HotelHold;
use App\Models\HotelReservation;
use App\Models\HotelRoomType;
use App\Models\Payment;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

final class HotelBookingService
{
    public function __construct(
        private readonly HotelPricingService $pricing,
        private readonly HotelInventoryService $inventory,
    ) {}

    public function search(Request $request): array
    {
        $city = trim((string) $request->input('city', $request->input('trip_to', $request->input('trip_from', ''))));
        $checkIn = $this->parseDate($request->input('check_in', $request->input('trip_date')));
        $checkOut = $this->parseDate($request->input('check_out', $request->input('return_date')));
        if (! $checkIn || ! $checkOut || $checkOut <= $checkIn) {
            return [];
        }

        $limit = max(1, min(100, (int) config('hotel.search_default_limit', 30)));
        $radiusKm = max(1.0, min(500.0, (float) config('hotel.search_radius_km', 50)));

        $q = Hotel::query()->where('status', 1);

        $lat = $request->input('lat');
        $lng = $request->input('lng');
        $hasGeo = is_numeric($lat) && is_numeric($lng);
        if ($hasGeo) {
            $latF = (float) $lat;
            $lngF = (float) $lng;
            $q->whereNotNull('lat')
                ->whereNotNull('lng')
                ->whereRaw(
                    '(6371 * acos(least(1.0, greatest(-1.0, cos(radians(?)) * cos(radians(lat)) * cos(radians(lng) - radians(?)) + sin(radians(?)) * sin(radians(lat)))))) <= ?',
                    [$latF, $lngF, $latF, $radiusKm]
                );
        }
        if ($city !== '') {
            $q->where(function ($w) use ($city) {
                $w->where('city', 'like', '%'.$city.'%')
                    ->orWhere('name', 'like', '%'.$city.'%');
            });
        }

        $out = [];
        foreach ($q->limit($limit)->get() as $hotel) {
            $min = HotelRoomType::query()
                ->where('hotel_id', $hotel->id)
                ->where('status', 1)
                ->min('base_price_per_night');
            if ($min === null) {
                continue;
            }
            $photo = $hotel->photos()->first();
            $out[] = [
                'id' => $hotel->id,
                'hotel_id' => $hotel->id,
                'name' => $hotel->name,
                'location' => $hotel->city ?? $hotel->address ?? '',
                'city' => $hotel->city,
                'photo' => $photo?->url ?? '',
                'stars' => (int) $hotel->star_rating,
                'rating' => (float) $hotel->aggregate_rating,
                'reviews' => (int) $hotel->review_count,
                'price_per_night' => (float) $min,
                'amenities' => [],
            ];
        }

        return $out;
    }

    public function hotelDetails(int $hotelId): ?array
    {
        $hotel = Hotel::query()->with(['photos', 'reviews', 'roomTypes.photos'])->find($hotelId);
        if (! $hotel || (int) $hotel->status !== 1) {
            return null;
        }

        return [
            'id' => $hotel->id,
            'name' => $hotel->name,
            'city' => $hotel->city,
            'address' => $hotel->address,
            'lat' => $hotel->lat,
            'lng' => $hotel->lng,
            'stars' => (int) $hotel->star_rating,
            'rating' => (float) $hotel->aggregate_rating,
            'review_count' => (int) $hotel->review_count,
            'description' => $hotel->description,
            'policies' => $hotel->policies,
            'photos' => $hotel->photos->map(fn ($p) => ['url' => $p->url, 'caption' => $p->caption])->values()->all(),
            'reviews' => $hotel->reviews->map(fn ($r) => [
                'author' => $r->author,
                'rating' => (float) $r->rating,
                'text' => $r->body,
                'date' => $r->reviewed_at?->toDateString(),
            ])->values()->all(),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function roomsForStay(Request $request, int $hotelId): array
    {
        $hotel = Hotel::query()->find($hotelId);
        if (! $hotel) {
            return [];
        }
        $checkIn = $this->parseDate($request->input('check_in', $request->input('trip_date')));
        $checkOut = $this->parseDate($request->input('check_out', $request->input('return_date')));
        $adults = max(1, (int) $request->input('adults', 2));
        $children = max(0, (int) $request->input('children', 0));
        if (! $checkIn || ! $checkOut || $checkOut <= $checkIn) {
            return [];
        }

        $types = HotelRoomType::query()
            ->where('hotel_id', $hotelId)
            ->where('status', 1)
            ->with('photos')
            ->get();

        $out = [];
        foreach ($types as $rt) {
            if ($adults + $children > (int) $rt->max_occupancy) {
                continue;
            }
            try {
                $this->inventory->assertAvailability($rt, $checkIn, $checkOut, 1);
                $available = true;
            } catch (\Throwable) {
                $available = false;
            }
            $quote = $this->pricing->quoteStay($rt, $checkIn, $checkOut, $adults, $children);
            $out[] = [
                'id' => $rt->id,
                'room_type_id' => $rt->id,
                'code' => $rt->code,
                'title' => $rt->title,
                'max_occupancy' => (int) $rt->max_occupancy,
                'bed_type' => $rt->bed_type,
                'amenities' => $rt->amenities ?? [],
                'photos' => $rt->photos->map(fn ($p) => ['url' => $p->url])->values()->all(),
                'available' => $available,
                'quote' => $quote,
            ];
        }

        return $out;
    }

    public function quote(Request $request): array
    {
        $rt = HotelRoomType::query()->findOrFail((int) $request->input('room_type_id'));
        $checkIn = $this->parseDate($request->input('check_in'));
        $checkOut = $this->parseDate($request->input('check_out'));
        $adults = max(1, (int) $request->input('adults', 2));
        $children = max(0, (int) $request->input('children', 0));
        if (! $checkIn || ! $checkOut || $checkOut <= $checkIn) {
            throw new \InvalidArgumentException('Invalid dates');
        }
        $this->inventory->assertAvailability($rt, $checkIn, $checkOut, 1);

        return $this->pricing->quoteStay($rt, $checkIn, $checkOut, $adults, $children);
    }

    /**
     * @throws \Throwable
     */
    public function createHold(User $user, array $input, string $idempotencyKey): HotelHold
    {
        $existing = HotelHold::query()
            ->where('idempotency_key', $idempotencyKey)
            ->where('user_id', $user->id)
            ->first();
        if ($existing) {
            return $existing;
        }

        $roomType = HotelRoomType::query()->findOrFail((int) $input['room_type_id']);
        $checkIn = $this->parseDate($input['check_in'] ?? null);
        $checkOut = $this->parseDate($input['check_out'] ?? null);
        $adults = max(1, (int) ($input['adults'] ?? 2));
        $children = max(0, (int) ($input['children'] ?? 0));
        if (! $checkIn || ! $checkOut || $checkOut <= $checkIn) {
            throw new \InvalidArgumentException('Invalid dates');
        }

        $quote = $this->pricing->quoteStay($roomType, $checkIn, $checkOut, $adults, $children);
        $ttl = max(5, (int) config('hotel.hold_ttl_minutes', 15));

        return DB::transaction(function () use ($user, $roomType, $checkIn, $checkOut, $adults, $children, $idempotencyKey, $quote, $ttl) {
            $this->inventory->applyHold($roomType, $checkIn, $checkOut, 1);

            return HotelHold::create([
                'user_id' => $user->id,
                'hotel_room_type_id' => $roomType->id,
                'check_in' => $checkIn->toDateString(),
                'check_out' => $checkOut->toDateString(),
                'adults' => $adults,
                'children' => $children,
                'idempotency_key' => $idempotencyKey,
                'expires_at' => now()->addMinutes($ttl),
                'status' => HotelHold::STATUS_PENDING,
                'total_amount' => $quote['total'],
                'quote_json' => $quote,
            ]);
        });
    }

    public function releaseHold(User $user, int $holdId): bool
    {
        $hold = HotelHold::query()->where('id', $holdId)->where('user_id', $user->id)->first();
        if (! $hold || $hold->status !== HotelHold::STATUS_PENDING) {
            return false;
        }

        return DB::transaction(function () use ($hold) {
            $roomType = $hold->roomType;
            $checkIn = Carbon::parse($hold->check_in);
            $checkOut = Carbon::parse($hold->check_out);
            $this->inventory->releaseHold($roomType, $checkIn, $checkOut, 1);
            $hold->update(['status' => HotelHold::STATUS_CANCELLED]);

            return true;
        });
    }

    /**
     * @return array{reservation: HotelReservation, booking: Booking, payment: Payment}
     */
    public function confirmFromHold(User $user, int $holdId): array
    {
        $hold = HotelHold::query()
            ->where('id', $holdId)
            ->where('user_id', $user->id)
            ->firstOrFail();

        if ($hold->status !== HotelHold::STATUS_PENDING || now()->greaterThan($hold->expires_at)) {
            throw new \RuntimeException('Hold is not valid');
        }

        $existing = HotelReservation::query()->where('hotel_hold_id', $hold->id)->first();
        if ($existing) {
            return $this->loadBookingPayment($existing);
        }

        return DB::transaction(function () use ($user, $hold) {
            $roomType = $hold->roomType;
            $hotel = $roomType->hotel;
            $checkIn = Carbon::parse($hold->check_in);
            $checkOut = Carbon::parse($hold->check_out);
            $quote = $hold->quote_json ?? $this->pricing->quoteStay(
                $roomType,
                $checkIn,
                $checkOut,
                (int) $hold->adults,
                (int) $hold->children
            );
            $total = (float) $quote['total'];

            $booking = Booking::create([
                'booking_date' => date('Y-m-d'),
                'customer_id' => $user->id,
                'user_id' => $user->id,
                'total_amount' => $total,
                'total_discount' => 0,
                'total_payable' => $total,
                'vat_amount' => (float) getOption('vat_amount', 0),
                'vat_total' => (float) ($quote['vat_amount'] ?? 0),
                'charge_amount' => (float) ($quote['charge_percent'] ?? 0),
                'charge_total' => (float) ($quote['charge_amount'] ?? 0),
                'booking_party' => 'jolzan',
                'platform' => 'android',
                'status' => AppConst::BOOKING_PENDING,
            ]);

            $paymentWindow = max(1, (int) config('hotel.payment_window_minutes', 10));
            $reservation = HotelReservation::create([
                'user_id' => $user->id,
                'hotel_hold_id' => $hold->id,
                'hotel_id' => $hotel->id,
                'hotel_room_type_id' => $roomType->id,
                'booking_id' => $booking->id,
                'check_in' => $hold->check_in,
                'check_out' => $hold->check_out,
                'adults' => $hold->adults,
                'children' => $hold->children,
                'total_payable' => $total,
                'currency' => $quote['currency'] ?? 'BDT',
                'status' => HotelReservation::STATUS_PENDING_PAYMENT,
                'quote_json' => $quote,
                'payment_due_at' => now()->addMinutes($paymentWindow),
            ]);

            $hold->update(['status' => HotelHold::STATUS_CONSUMED]);

            $payment = Payment::create([
                'booking_id' => $booking->id,
                'transaction_id' => strtoupper(uniqid((string) $booking->id, false)),
                'customer_id' => $user->id,
                'status' => 'pending',
                'paid_amount' => $total,
                'dues' => 0,
                'store_amount' => 0,
            ]);

            return compact('reservation', 'booking', 'payment');
        });
    }

    /**
     * @return array{reservation: HotelReservation, booking: Booking, payment: Payment}
     */
    private function loadBookingPayment(HotelReservation $reservation): array
    {
        $booking = $reservation->booking;
        if (! $booking) {
            throw new \RuntimeException('Booking missing for reservation');
        }
        $payment = Payment::query()->where('booking_id', $booking->id)->orderByDesc('id')->first();
        if (! $payment) {
            throw new \RuntimeException('Payment missing for reservation');
        }

        return ['reservation' => $reservation, 'booking' => $booking, 'payment' => $payment];
    }

    public function expireStaleHolds(): int
    {
        $n = 0;
        $stale = HotelHold::query()
            ->where('status', HotelHold::STATUS_PENDING)
            ->where('expires_at', '<', now())
            ->get();

        foreach ($stale as $hold) {
            try {
                DB::transaction(function () use ($hold, &$n) {
                    $roomType = $hold->roomType;
                    $checkIn = Carbon::parse($hold->check_in);
                    $checkOut = Carbon::parse($hold->check_out);
                    $this->inventory->releaseHold($roomType, $checkIn, $checkOut, 1);
                    $hold->update(['status' => HotelHold::STATUS_EXPIRED]);
                    $n++;
                });
            } catch (\Throwable) {
            }
        }

        return $n;
    }

    public function failUnpaidReservations(): int
    {
        $n = 0;
        $rows = HotelReservation::query()
            ->where('status', HotelReservation::STATUS_PENDING_PAYMENT)
            ->whereNotNull('payment_due_at')
            ->where('payment_due_at', '<', now())
            ->get();

        foreach ($rows as $res) {
            try {
                DB::transaction(function () use ($res, &$n) {
                    $roomType = $res->roomType;
                    $checkIn = Carbon::parse($res->check_in);
                    $checkOut = Carbon::parse($res->check_out);
                    $this->inventory->releaseHold($roomType, $checkIn, $checkOut, 1);
                    $res->update(['status' => HotelReservation::STATUS_FAILED]);
                    if ($res->booking) {
                        $res->booking->update(['status' => AppConst::BOOKING_FAILED]);
                    }
                    Payment::query()->where('booking_id', $res->booking_id)->update(['status' => 'failed']);
                    $n++;
                });
            } catch (\Throwable) {
            }
        }

        return $n;
    }

    private function parseDate(mixed $raw): ?Carbon
    {
        if ($raw === null || $raw === '') {
            return null;
        }
        try {
            return Carbon::parse($raw)->startOfDay();
        } catch (\Throwable) {
            return null;
        }
    }
}
