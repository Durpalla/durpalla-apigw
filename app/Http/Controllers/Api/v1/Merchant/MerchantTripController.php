<?php

namespace App\Http\Controllers\Api\v1\Merchant;

use App\Constants\AppConst;
use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\BookingItem;
use App\Models\Vehicle;
use App\Models\VehicleRoute;
use App\Models\VehicleSchedule;
use App\Repository\Interfaces\ScheduleRepositoryInterface;
use App\Services\CalculationService;
use App\Support\ResolvesMerchantOwner;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Merchant Desk Pro — create trips (vehicle schedules) using the same rules as TripService.
 */
class MerchantTripController extends Controller
{
    use ResolvesMerchantOwner;

    public function __construct(
        private CalculationService $calculation,
        private ScheduleRepositoryInterface $scheduleRepository,
    ) {}

    /**
     * List trips (vehicle schedules) for this merchant.
     *
     * Query:
     * - date (optional): YYYY-MM-DD to filter a single day
     * - from (optional): YYYY-MM-DD
     * - to (optional): YYYY-MM-DD
     * - vehicle_id (optional): limit to one property/vehicle
     *
     * Default (no date/from/to): upcoming trips (schedule_date >= today).
     */
    public function index(Request $request): JsonResponse
    {
        $ownerId = $this->merchantOwnerId($request);

        $request->validate([
            'date' => 'nullable|date_format:Y-m-d',
            'from' => 'nullable|date_format:Y-m-d',
            'to' => 'nullable|date_format:Y-m-d',
            'vehicle_id' => 'nullable|integer|exists:vehicles,id',
        ]);

        $q = VehicleSchedule::query()
            ->where('merchant_id', $ownerId)
            ->with([
                'route:id,route_name',
                'launch:id,name',
                'startingPoint.ghat:id,name',
                'endingPoint.ghat:id,name',
                'startFrom:id,name',
                'stopTo:id,name',
                'boardingVias' => fn ($q) => $q->orderBy('serial_num')->with('ghat:id,name'),
            ]);

        if ($request->filled('vehicle_id')) {
            $q->where('vehicle_id', (int) $request->vehicle_id);
        }

        if ($request->filled('date')) {
            $q->whereDate('schedule_date', $request->date);
        } elseif ($request->filled('from') || $request->filled('to')) {
            if ($request->filled('from')) {
                $q->whereDate('schedule_date', '>=', $request->from);
            }
            if ($request->filled('to')) {
                $q->whereDate('schedule_date', '<=', $request->to);
            }
        } else {
            $q->whereDate('schedule_date', '>=', now()->toDateString());
        }

        $items = $q
            ->orderBy('schedule_date')
            ->orderBy('leaving_at')
            ->get()
            ->map(fn (VehicleSchedule $t) => $this->transformTrip($t))
            ->values();

        return response()->json([
            'success' => true,
            'data' => $items,
        ]);
    }

    /**
     * Single trip detail for the authenticated merchant (dashboard schedule "Info" style).
     */
    public function show(Request $request, int $id): JsonResponse
    {
        $ownerId = $this->merchantOwnerId($request);

        $trip = VehicleSchedule::query()
            ->where('merchant_id', $ownerId)
            ->where('id', $id)
            ->with([
                'route:id,route_name',
                'launch:id,name,vehicle_type,passengers_capacity,registration_no,registration_expiry_date,fitness_expiry_date,photo',
                'startingPoint.ghat:id,name',
                'endingPoint.ghat:id,name',
                'boardingVias' => fn ($q) => $q->orderBy('serial_num')->with('ghat:id,name'),
                'startFrom:id,name',
                'stopTo:id,name',
                'ticketBookings.deck.departureFrom.ghat:id,name',
                'ticketBookings.deck.departureTo.ghat:id,name',
            ])
            ->withCount([
                'cabinMappings',
                'seatMappings',
                'cabinBookings',
                'seatBookings',
            ])
            ->first();

        if (! $trip) {
            return response()->json([
                'success' => false,
                'message' => 'Trip not found.',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $this->transformTripDetail($trip),
        ]);
    }

    /**
     * GET /merchant/trips/{id}/stats — same aggregates as supervisor GET /trips/{tripId}/stats, merchant-scoped.
     */
    public function stats(Request $request, int $id): JsonResponse
    {
        $ownerId = $this->merchantOwnerId($request);

        $trip = VehicleSchedule::query()
            ->where('merchant_id', $ownerId)
            ->where('id', $id)
            ->first();

        if (! $trip) {
            return response()->json([
                'success' => false,
                'message' => 'Trip not found.',
            ], 404);
        }

        $ticketsBooked = BookingItem::where('trip_id', $trip->id)->where('status', AppConst::BOOKING_ITEM_ACTIVE)->count();
        $totalCapacity = $trip->seatCount() + $trip->cabinCount();
        $ticketsRemaining = max(0, $totalCapacity - $ticketsBooked);

        $bookings = Booking::whereHas('bookingItems', fn ($q) => $q->where('trip_id', $trip->id)->where('status', AppConst::BOOKING_ITEM_ACTIVE))->get();
        $cashOnHand = 0;
        $digitalPayments = 0;
        foreach ($bookings as $b) {
            $payment = $b->payment;
            if ($payment) {
                if (in_array(strtolower((string) ($payment->payment_method ?? '')), ['cash'], true)) {
                    $cashOnHand += (float) ($payment->paid_amount ?? 0);
                } else {
                    $digitalPayments += (float) ($payment->paid_amount ?? 0);
                }
            }
        }

        $totalTickets = max(1, $totalCapacity);
        $progress = $totalTickets > 0 ? round($ticketsBooked / $totalTickets, 2) : 0;

        return response()->json([
            'success' => true,
            'data' => [
                'trip_id' => 'TRIP-'.str_pad((string) $trip->id, 3, '0', STR_PAD_LEFT),
                'tickets_booked' => $ticketsBooked,
                'tickets_remaining' => $ticketsRemaining,
                'cash_on_hand_bdt' => (int) round($cashOnHand),
                'digital_payments_bdt' => (int) round($digitalPayments),
                'progress' => $progress,
            ],
        ]);
    }

    /**
     * Create a single trip (schedule).
     *
     * @bodyParam departure_at string ISO 8601 datetime (local server parse)
     * @bodyParam is_reverse bool When true, trip runs reverse direction (Down)
     */
    public function store(Request $request): JsonResponse
    {
        $ownerId = $this->merchantOwnerId($request);

        $validated = $request->validate([
            'vehicle_id' => 'required|integer|exists:vehicles,id',
            'route_id' => 'required|integer|exists:vehicle_routes,id',
            'departure_at' => 'required|date',
            'is_reverse' => 'sometimes|boolean',
            'operation_hour' => 'nullable|numeric|min:0|max:168',
        ]);

        $vehicle = Vehicle::where('merchant_id', $ownerId)->findOrFail($validated['vehicle_id']);

        if ((int) $vehicle->route_id !== (int) $validated['route_id']) {
            return response()->json([
                'success' => false,
                'message' => 'Selected route must match the property (vehicle) route.',
            ], 422);
        }

        $route = VehicleRoute::with(['startingPoint', 'endingPoint'])->findOrFail($validated['route_id']);

        $start = $route->startingPoint;
        $end = $route->endingPoint;
        if (! $start || ! $end) {
            return response()->json([
                'success' => false,
                'message' => 'Route is missing start or end points.',
            ], 422);
        }

        $dt = Carbon::parse($validated['departure_at']);
        $schedule_date_input = $dt->format('d/m/Y');
        $schedule_time_input = $dt->format('H:i');
        $operationHour = (float) ($validated['operation_hour'] ?? 12);

        $data = [
            'route_id' => $route->id,
            'vehicle_id' => $vehicle->id,
            'schedule_date' => $schedule_date_input,
            'schedule_time' => $schedule_time_input,
            'operation_hour' => $operationHour,
        ];

        if (! empty($validated['is_reverse'])) {
            $data['schedule_type'] = 1;
        }

        try {
            DB::transaction(function () use ($data) {
                $vehicle = Vehicle::findOrFail($data['vehicle_id']);
                $schedule_type = array_key_exists('schedule_type', $data) ? 'reverse' : 'straight';
                $schedule_date = $this->calculation->createDate($data['schedule_date']);
                $schedule_time = $schedule_date.' '.date('H:i:s', strtotime($data['schedule_time']));

                $existing = VehicleSchedule::where([
                    'schedule_date' => $schedule_date,
                    'vehicle_id' => $data['vehicle_id'],
                    'status' => AppConst::SCHEDULE_ACTIVE,
                    'schedule_type' => $schedule_type,
                ])->first();

                if ($existing) {
                    throw new \RuntimeException(
                        'A trip already exists for this property, date, and direction (Up/Down).'
                    );
                }

                $route = VehicleRoute::with(['startingPoint', 'endingPoint'])->findOrFail($data['route_id']);
                $operation_time = strtotime($schedule_time) + (int) (60 * 60 * (float) $data['operation_hour']);

                $this->scheduleRepository->create(array_merge($data, [
                    'user_id' => auth()->id(),
                    'merchant_id' => $vehicle->merchant_id,
                    'schedule_date' => $schedule_date,
                    'leaving_at' => $schedule_time,
                    'starting_point' => ($schedule_type === 'reverse')
                        ? $route->endingPoint->ghat_id
                        : $route->startingPoint->ghat_id,
                    'ending_point' => ($schedule_type === 'reverse')
                        ? $route->startingPoint->ghat_id
                        : $route->endingPoint->ghat_id,
                    'operation_timeline' => date('Y-m-d H:i:s', $operation_time),
                    'schedule_type' => $schedule_type,
                ]));
            });
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }

        $trip = VehicleSchedule::where('vehicle_id', $vehicle->id)
            ->where('route_id', $route->id)
            ->orderByDesc('id')
            ->with(['route:id,route_name', 'launch:id,name'])
            ->first();

        return response()->json([
            'success' => true,
            'message' => 'Trip created.',
            'data' => $trip ? $this->transformTrip($trip) : null,
        ], 201);
    }

    private function transformTrip(VehicleSchedule $t): array
    {
        $ghats = $this->departureArrivalGhatNames($t);
        $viaPoints = [];
        foreach ($t->boardingVias as $via) {
            $name = $via->ghat?->name;
            if ($name) {
                $viaPoints[] = $name;
            }
        }
        if ($t->schedule_type === 'reverse') {
            $viaPoints = array_reverse($viaPoints);
        }

        return [
            'id' => (string) $t->id,
            'vehicle_id' => (string) $t->vehicle_id,
            'vehicle_name' => $t->launch?->name ?? '',
            'route_id' => (string) $t->route_id,
            'route_name' => $t->route?->route_name ?? '',
            'schedule_date' => $t->schedule_date instanceof \DateTimeInterface
                ? $t->schedule_date->format('Y-m-d')
                : (string) $t->schedule_date,
            'leaving_at' => $t->leaving_at instanceof \DateTimeInterface
                ? $t->leaving_at->format('Y-m-d H:i:s')
                : (string) $t->leaving_at,
            'schedule_type' => $t->schedule_type,
            'status' => $t->status,
            'boarding_point' => $ghats['departure_ghat_name'],
            'dropping_point' => $ghats['arrival_ghat_name'],
            'via_points' => array_values($viaPoints),
        ];
    }

    private function transformTripDetail(VehicleSchedule $t): array
    {
        $base = $this->transformTrip($t);
        $vehicle = $t->launch;
        $capacity = (int) ($vehicle?->passengers_capacity ?? 0);

        $deckPassengers = 0;
        foreach ($t->ticketBookings as $item) {
            $deckPassengers += $this->passengerPersonCount($item->passenger);
        }

        $stoppages = $this->stoppageNames($t);
        $routeDisplay = $this->routeDisplayLine($t);
        $ghats = $this->departureArrivalGhatNames($t);
        $bookingCollected = $this->sumBookingCollectedForTrip($t);
        $deckSegments = $this->buildDeckGhatSegments($t);

        $opEnd = $t->operation_timeline;
        if ($opEnd instanceof \DateTimeInterface) {
            $opEnd = $opEnd->format('Y-m-d H:i:s');
        }

        return array_merge($base, [
            'vehicle_type' => $vehicle?->vehicle_type ?? '',
            'passengers_capacity' => $capacity,
            'vehicle_registration_no' => $vehicle?->registration_no,
            'registration_expiry_date' => $vehicle?->registration_expiry_date,
            'fitness_expiry_date' => $vehicle?->fitness_expiry_date,
            'vehicle_photo' => $vehicle?->photo,
            'vehicle_photo_url' => $this->vehiclePhotoUrl($vehicle?->photo),
            'operation_hour' => $t->operation_hour !== null ? (float) $t->operation_hour : null,
            'operation_timeline' => $opEnd,
            'route_display' => $routeDisplay,
            'stoppages' => $stoppages,
            'departure_ghat_name' => $ghats['departure_ghat_name'],
            'arrival_ghat_name' => $ghats['arrival_ghat_name'],
            'booking_collected_total_bdt' => round($bookingCollected, 2),
            'cabin_bookings' => (int) $t->cabin_bookings_count,
            'cabin_inventory' => (int) $t->cabin_mappings_count,
            'seat_bookings' => (int) $t->seat_bookings_count,
            'seat_inventory' => (int) $t->seat_mappings_count,
            'deck_passengers_booked' => $deckPassengers,
            'deck_ghat_segments' => $deckSegments,
        ]);
    }

    /**
     * @return array{departure_ghat_name: string, arrival_ghat_name: string}
     */
    private function departureArrivalGhatNames(VehicleSchedule $schedule): array
    {
        $start = $schedule->startFrom?->name ?? $schedule->startingPoint?->ghat?->name ?? '';
        $end = $schedule->stopTo?->name ?? $schedule->endingPoint?->ghat?->name ?? '';

        return [
            'departure_ghat_name' => $start,
            'arrival_ghat_name' => $end,
        ];
    }

    private function sumBookingCollectedForTrip(VehicleSchedule $t): float
    {
        $ids = BookingItem::query()
            ->where('trip_id', $t->id)
            ->where('status', AppConst::BOOKING_ITEM_ACTIVE)
            ->distinct()
            ->pluck('booking_id');

        $sum = 0.0;
        foreach (Booking::query()->whereIn('id', $ids)->get() as $booking) {
            $p = $booking->payment;
            if ($p) {
                $sum += (float) ($p->paid_amount ?? 0);
            }
        }

        return $sum;
    }

    /**
     * @return list<array{segment_label: string, passengers: int, amount_bdt: float, fare_per_person: float|null}>
     */
    private function buildDeckGhatSegments(VehicleSchedule $t): array
    {
        $items = BookingItem::query()
            ->where('trip_id', $t->id)
            ->where('booking_type', 'deck')
            ->where('status', AppConst::BOOKING_ITEM_ACTIVE)
            ->with(['deck.departureFrom.ghat', 'deck.departureTo.ghat'])
            ->get();

        $isReverse = $t->schedule_type === 'reverse';

        return $items->groupBy(function (BookingItem $item) {
            return $item->deck_fare_id ? 'df_'.$item->deck_fare_id : 'rn_'.md5((string) ($item->route_name ?? ''));
        })->map(function ($group) use ($isReverse) {
            /** @var \Illuminate\Support\Collection<int, BookingItem> $group */
            $first = $group->first();
            $passengers = 0;
            $amount = 0.0;
            foreach ($group as $item) {
                $passengers += $this->passengerPersonCount($item->passenger);
                $amount += (float) ($item->price ?? 0);
            }
            $farePerPerson = null;
            $segmentLabel = '';
            if ($first->deck_fare_id && $first->deck) {
                $df = $first->deck;
                $from = $df->departureFrom?->ghat?->name ?? '';
                $to = $df->departureTo?->ghat?->name ?? '';
                $segmentLabel = trim($from.' → '.$to);
                $fare = $isReverse
                    ? (float) ($df->reverse_fare ?? $df->fare ?? 0)
                    : (float) ($df->fare ?? 0);
                $farePerPerson = $fare > 0 ? round($fare, 2) : null;
            } else {
                $segmentLabel = trim((string) ($first->route_name ?? '')) ?: 'Deck';
            }

            return [
                'segment_label' => $segmentLabel,
                'passengers' => $passengers,
                'amount_bdt' => round($amount, 2),
                'fare_per_person' => $farePerPerson,
            ];
        })->values()->all();
    }

    private function passengerPersonCount(mixed $raw): int
    {
        if (is_string($raw)) {
            $decoded = json_decode($raw, false);
            return (int) ($decoded->person ?? 0);
        }
        if (is_array($raw)) {
            return (int) ($raw['person'] ?? 0);
        }

        return 0;
    }

    /**
     * Terminal order for this trip (same logic as admin launch schedule view).
     *
     * @return list<string>
     */
    private function stoppageNames(VehicleSchedule $schedule): array
    {
        $names = [];
        $start = $schedule->startingPoint?->ghat?->name;
        if ($start) {
            $names[] = $start;
        }
        foreach ($schedule->boardingVias as $via) {
            $n = $via->ghat?->name;
            if ($n) {
                $names[] = $n;
            }
        }
        $end = $schedule->endingPoint?->ghat?->name;
        if ($end) {
            $names[] = $end;
        }
        $names = array_values(array_unique($names));
        if ($schedule->schedule_type === 'reverse') {
            $names = array_reverse($names);
        }

        return $names;
    }

    private function routeDisplayLine(VehicleSchedule $trip): string
    {
        return $trip->tripDirectionRouteLine(' → ');
    }

    private function vehiclePhotoUrl(?string $photo): ?string
    {
        if ($photo === null || $photo === '') {
            return null;
        }
        if (str_starts_with($photo, 'http://') || str_starts_with($photo, 'https://')) {
            return $photo;
        }
        if (str_contains($photo, '/')) {
            return upload_asset(ltrim($photo, '/'));
        }

        return upload_asset('vehicles/'.$photo);
    }
}
