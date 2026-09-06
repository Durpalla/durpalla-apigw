<?php

namespace App\Services\Hotel;

use App\Models\HotelHold;
use App\Models\HotelRoomType;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use App\Models\Hotel;
use App\Models\HotelRoom;
use App\Models\RoomRatePlan;

/**
 * Merchant desk soft-hold on shared hotel_holds + hotel_inventory (same SoT as apigw).
 */
class MerchantHotelHoldService
{
    public function __construct(
        private readonly HotelInventoryService $inventory,
    ) {}

    /**
     * @param  array<string, mixed>  $input
     *
     * @throws \InvalidArgumentException|\RuntimeException
     */
    public function createHold(
        int $merchantOwnerId,
        ?int $actorId,
        ?string $actorType,
        array $input,
        string $idempotencyKey,
    ): HotelHold {
        $existing = HotelHold::query()
            ->where('merchant_owner_id', $merchantOwnerId)
            ->where('idempotency_key', $idempotencyKey)
            ->first();
        if ($existing) {
            return $existing;
        }

        $hotelId = (int) ($input['hotel_id'] ?? 0);
        $checkIn = Carbon::parse((string) ($input['check_in_date'] ?? $input['check_in'] ?? ''))->startOfDay();
        $checkOut = Carbon::parse((string) ($input['check_out_date'] ?? $input['check_out'] ?? ''))->startOfDay();
        $adults = max(1, (int) ($input['adults'] ?? 1));
        $children = max(0, (int) ($input['children'] ?? 0));
        $rooms = $input['rooms'] ?? null;
        $topChildrenAges = $this->normalizeChildrenAges($input['children_ages'] ?? null);

        if ($hotelId <= 0 || ! is_array($rooms) || $rooms === []) {
            throw new \InvalidArgumentException('hotel_id and rooms are required');
        }
        if ($checkOut->lte($checkIn)) {
            throw new \InvalidArgumentException('Check-out must be after check-in');
        }

        $hotel = Hotel::query()
            ->where('id', $hotelId)
            ->where('merchant_id', $merchantOwnerId)
            ->firstOrFail();

        $merged = [];
        $lineMeta = [];
        foreach ($rooms as $row) {
            if (! is_array($row)) {
                continue;
            }
            $roomId = (int) ($row['room_id'] ?? $row['hotel_room_id'] ?? 0);
            $roomTypeId = (int) ($row['room_type_id'] ?? 0);
            $ratePlanId = (int) ($row['rate_plan_id'] ?? 0);
            $qty = max(1, min(20, (int) ($row['quantity'] ?? 1)));

            if ($roomId > 0) {
                $hotelRoom = HotelRoom::query()
                    ->where('id', $roomId)
                    ->where('hotel_id', $hotel->id)
                    ->first();
                if (! $hotelRoom) {
                    throw new \InvalidArgumentException('Invalid room for this hotel');
                }
                $roomTypeId = (int) $hotelRoom->room_type_id;
                if ($ratePlanId <= 0) {
                    $ratePlanId = (int) RoomRatePlan::query()
                        ->where('room_type_id', $roomTypeId)
                        ->where('status', 1)
                        ->orderBy('id')
                        ->value('id');
                }
            }

            if ($roomId <= 0 || $roomTypeId <= 0 || $ratePlanId <= 0) {
                throw new \InvalidArgumentException('Each room line needs room_id, room_type_id and rate_plan_id');
            }

            $key = (string) $roomId;
            $merged[$key] = ($merged[$key] ?? 0) + $qty;
            $rowAges = $this->normalizeChildrenAges($row['children_ages'] ?? null);
            $lineMeta[$key] = [
                'hotel_id' => (int) $hotel->id,
                'room_id' => $roomId,
                'room_type_id' => $roomTypeId,
                'rate_plan_id' => $ratePlanId,
                'adults' => max(1, (int) ($row['adults'] ?? $adults)),
                'children' => max(0, (int) ($row['children'] ?? $children)),
                'children_ages' => $rowAges !== [] ? $rowAges : $topChildrenAges,
            ];
        }

        if ($merged === []) {
            throw new \InvalidArgumentException('No valid room lines');
        }

        $guests = $adults + $children;
        $bedCapacity = 0;
        foreach ($merged as $roomIdKey => $qty) {
            $hotelRoom = HotelRoom::query()->find((int) $lineMeta[$roomIdKey]['room_id']);
            $bedCapacity += max(1, (int) ($hotelRoom->max_occupancy ?? 2)) * (int) $qty;
        }
        if ($guests > $bedCapacity) {
            throw new \InvalidArgumentException(
                "Selected rooms sleep {$bedCapacity} guests but the party has {$guests}. Increase quantity or add another room."
            );
        }

        $nights = max(1, (int) $checkIn->diffInDays($checkOut));
        $ttl = max(5, (int) config('hotel.merchant_hold_ttl_minutes', config('hotel.hold_ttl_minutes', 15)));

        return DB::transaction(function () use (
            $merchantOwnerId,
            $actorId,
            $actorType,
            $hotel,
            $checkIn,
            $checkOut,
            $adults,
            $children,
            $topChildrenAges,
            $idempotencyKey,
            $merged,
            $lineMeta,
            $nights,
            $ttl,
        ) {
            $this->expireStaleForOwner($merchantOwnerId);

            $lineOutputs = [];
            $grandTotal = 0.0;
            $resolved = [];

            foreach ($merged as $roomIdKey => $qty) {
                $meta = $lineMeta[$roomIdKey];
                $hotelRoom = HotelRoom::query()->findOrFail((int) $meta['room_id']);
                $apiRt = $this->ensureApiRoomType($hotelRoom);
                $unit = (float) ($hotelRoom->base_price ?? $apiRt->base_price_per_night ?? 0);
                $childrenAges = is_array($meta['children_ages'] ?? null) ? $meta['children_ages'] : [];
                $childPrice = 0.0;
                if ($childrenAges !== []) {
                    $ratePlan = RoomRatePlan::query()->find((int) $meta['rate_plan_id']);
                    $engine = new ChildRuleEngine($hotel, $ratePlan, $unit, $childrenAges, $nights);
                    $validation = $engine->validate();
                    if (! $validation['valid']) {
                        throw new \InvalidArgumentException(implode(', ', $validation['errors']));
                    }
                    $childPrice = (float) $engine->calculateChildPrice();
                }
                // Match confirm expansion: each qty unit gets the same child ages / fees.
                $lineTotal = round(($unit * $nights + $childPrice) * $qty, 2);
                $grandTotal += $lineTotal;

                $resolved[] = ['room_type' => $apiRt, 'quantity' => $qty];
                $lineOutputs[] = [
                    'room_type_id' => $apiRt->id,
                    'quantity' => $qty,
                    'code' => $apiRt->code,
                    'title' => $apiRt->title,
                    'module_room_id' => (int) $meta['room_id'],
                    'module_room_type_id' => (int) $meta['room_type_id'],
                    'rate_plan_id' => (int) $meta['rate_plan_id'],
                    'hotel_id' => (int) $hotel->id,
                    'unit_price' => $unit,
                    'child_price' => round($childPrice, 2),
                    'line_total' => $lineTotal,
                    'adults' => (int) ($meta['adults'] ?? $adults),
                    'children' => (int) ($meta['children'] ?? $children),
                    'children_ages' => $childrenAges,
                ];
            }

            usort($resolved, fn (array $a, array $b): int => $a['room_type']->id <=> $b['room_type']->id);

            foreach ($resolved as $entry) {
                $this->inventory->applyHold($entry['room_type'], $checkIn, $checkOut, $entry['quantity']);
            }

            $primary = $resolved[0]['room_type'];

            return HotelHold::create([
                'user_id' => null,
                'agent_id' => null,
                'merchant_owner_id' => $merchantOwnerId,
                'hotel_room_type_id' => $primary->id,
                'check_in' => $checkIn->toDateString(),
                'check_out' => $checkOut->toDateString(),
                'adults' => $adults,
                'children' => $children,
                'idempotency_key' => $idempotencyKey,
                'expires_at' => now()->addMinutes($ttl),
                'status' => HotelHold::STATUS_PENDING,
                'total_amount' => round($grandTotal, 2),
                'quote_json' => [
                    'source' => 'merchant_desk',
                    'actor_id' => $actorId,
                    'actor_type' => $actorType,
                    'hotel_id' => (int) $hotel->id,
                    'multi_room' => count($lineOutputs) > 1 || ($lineOutputs[0]['quantity'] ?? 1) > 1,
                    'lines' => $lineOutputs,
                    'total' => round($grandTotal, 2),
                    'nights' => $nights,
                    'adults' => $adults,
                    'children' => $children,
                    'children_ages' => $topChildrenAges,
                ],
            ]);
        });
    }

    public function releaseHold(HotelHold $hold): HotelHold
    {
        if ($hold->status !== HotelHold::STATUS_PENDING) {
            return $hold;
        }

        return DB::transaction(function () use ($hold) {
            $hold = HotelHold::query()->lockForUpdate()->findOrFail($hold->id);
            if ($hold->status !== HotelHold::STATUS_PENDING) {
                return $hold;
            }

            $this->releaseInventoryForHold($hold);
            $hold->update(['status' => HotelHold::STATUS_CANCELLED]);

            return $hold->fresh();
        });
    }

    /**
     * Confirm a pending hold inside one DB transaction:
     * lock hold → build rooms → run $book → finalize inventory + mark consumed.
     * If $book throws (or anything fails), the whole unit rolls back and the hold stays pending for retry.
     *
     * @param  callable(HotelHold, list<array<string, mixed>>): mixed  $book
     */
    public function confirmWithBooking(HotelHold $hold, callable $book): mixed
    {
        return DB::transaction(function () use ($hold, $book) {
            $hold = HotelHold::query()->lockForUpdate()->findOrFail($hold->id);
            $this->assertUsableOrFail($hold);

            $roomsPayload = $this->buildRoomsPayload($hold);
            $result = $book($hold, $roomsPayload);

            $hold = HotelHold::query()->lockForUpdate()->findOrFail($hold->id);
            $this->assertUsableOrFail($hold);

            $checkIn = Carbon::parse($hold->check_in)->startOfDay();
            $checkOut = Carbon::parse($hold->check_out)->startOfDay();
            $quote = is_array($hold->quote_json) ? $hold->quote_json : [];
            $lines = is_array($quote['lines'] ?? null) ? $quote['lines'] : [];

            foreach ($lines as $line) {
                $qty = max(1, (int) ($line['quantity'] ?? 1));
                $apiRoomTypeId = (int) ($line['room_type_id'] ?? 0);
                $rt = HotelRoomType::query()->find($apiRoomTypeId);
                if (! $rt) {
                    throw new \RuntimeException('Hold room type missing');
                }
                $this->inventory->finalizeFromHold($rt, $checkIn, $checkOut, $qty);
            }

            $hold->update(['status' => HotelHold::STATUS_CONSUMED]);

            return $result;
        });
    }

    /**
     * Build booking room rows from a pending hold without consuming it.
     *
     * @return list<array<string, mixed>>
     */
    public function roomsPayloadForConfirm(HotelHold $hold): array
    {
        $this->assertUsableOrFail($hold);

        return DB::transaction(function () use ($hold) {
            $hold = HotelHold::query()->lockForUpdate()->findOrFail($hold->id);
            $this->assertUsableOrFail($hold);

            return $this->buildRoomsPayload($hold);
        });
    }

    /**
     * Convert hold → sold on shared inventory and mark consumed.
     */
    public function finalizeHoldAfterBooking(HotelHold $hold): void
    {
        DB::transaction(function () use ($hold) {
            $hold = HotelHold::query()->lockForUpdate()->findOrFail($hold->id);
            if ($hold->status === HotelHold::STATUS_CONSUMED) {
                return;
            }
            $this->assertUsableOrFail($hold);

            $checkIn = Carbon::parse($hold->check_in)->startOfDay();
            $checkOut = Carbon::parse($hold->check_out)->startOfDay();
            $quote = is_array($hold->quote_json) ? $hold->quote_json : [];
            $lines = is_array($quote['lines'] ?? null) ? $quote['lines'] : [];

            foreach ($lines as $line) {
                $qty = max(1, (int) ($line['quantity'] ?? 1));
                $apiRoomTypeId = (int) ($line['room_type_id'] ?? 0);
                $rt = HotelRoomType::query()->find($apiRoomTypeId);
                if (! $rt) {
                    throw new \RuntimeException('Hold room type missing');
                }
                $this->inventory->finalizeFromHold($rt, $checkIn, $checkOut, $qty);
            }

            $hold->update(['status' => HotelHold::STATUS_CONSUMED]);
        });
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function consumeHoldForConfirm(HotelHold $hold): array
    {
        return $this->confirmWithBooking($hold, static fn (HotelHold $h, array $rooms): array => $rooms);
    }

    /**
     * @return list<array<string, mixed>>
     */
    protected function buildRoomsPayload(HotelHold $hold): array
    {
        $quote = is_array($hold->quote_json) ? $hold->quote_json : [];
        $lines = is_array($quote['lines'] ?? null) ? $quote['lines'] : [];
        if ($lines === []) {
            throw new \RuntimeException('Hold has no room lines to confirm.');
        }

        $roomsPayload = [];
        foreach ($lines as $line) {
            $qty = max(1, (int) ($line['quantity'] ?? 1));
            $apiRoomTypeId = (int) ($line['room_type_id'] ?? 0);
            if (! HotelRoomType::query()->whereKey($apiRoomTypeId)->exists()) {
                throw new \RuntimeException('Hold room type missing');
            }

            $moduleRoomTypeId = (int) ($line['module_room_type_id'] ?? 0);
            $moduleRoomId = (int) ($line['module_room_id'] ?? 0);
            $ratePlanId = (int) ($line['rate_plan_id'] ?? 0);
            $hotelId = (int) ($line['hotel_id'] ?? $quote['hotel_id'] ?? 0);
            if ($hotelId < 1 || $moduleRoomTypeId < 1 || $ratePlanId < 1) {
                throw new \RuntimeException(
                    'Hold is missing hotel, room type, or rate plan. Release it and create a new hold.'
                );
            }

            $lineAdults = max(1, (int) ($line['adults'] ?? $hold->adults));
            $lineChildren = max(0, (int) ($line['children'] ?? $hold->children));
            $lineAges = $this->normalizeChildrenAges($line['children_ages'] ?? ($quote['children_ages'] ?? null));

            for ($i = 0; $i < $qty; $i++) {
                $roomsPayload[] = [
                    'hotel_id' => $hotelId,
                    'room_type_id' => $moduleRoomTypeId,
                    'rate_plan_id' => $ratePlanId,
                    'room_id' => $moduleRoomId > 0 ? $moduleRoomId : null,
                    'adults' => $lineAdults,
                    'children' => $lineChildren,
                    'children_ages' => $lineAges,
                ];
            }
        }

        return $roomsPayload;
    }

    protected function assertUsableOrFail(HotelHold $hold): void
    {
        if ($hold->status === HotelHold::STATUS_CONSUMED) {
            throw new \RuntimeException(
                'This hold was already used for a booking. Open Bookings or create a new hold.'
            );
        }
        if ($hold->status === HotelHold::STATUS_CANCELLED) {
            throw new \RuntimeException('This hold was released. Create a new hold.');
        }
        if ($hold->status === HotelHold::STATUS_EXPIRED
            || ! $hold->expires_at
            || $hold->expires_at->isPast()) {
            throw new \RuntimeException('Hold has expired. Create a new hold and confirm within the hold time.');
        }
        if ($hold->status !== HotelHold::STATUS_PENDING) {
            throw new \RuntimeException('Hold is not valid or has expired');
        }
    }

    public function expireStaleForOwner(int $merchantOwnerId): int
    {
        if (! Schema::hasTable('hotel_holds')) {
            return 0;
        }

        $stale = HotelHold::query()
            ->where('merchant_owner_id', $merchantOwnerId)
            ->where('status', HotelHold::STATUS_PENDING)
            ->where('expires_at', '<=', now())
            ->get();

        $count = 0;
        foreach ($stale as $hold) {
            DB::transaction(function () use ($hold, &$count) {
                $locked = HotelHold::query()->lockForUpdate()->find($hold->id);
                if (! $locked || $locked->status !== HotelHold::STATUS_PENDING) {
                    return;
                }
                $this->releaseInventoryForHold($locked);
                $locked->update(['status' => HotelHold::STATUS_EXPIRED]);
                $count++;
            });
        }

        return $count;
    }

    public function isUsable(HotelHold $hold): bool
    {
        return $hold->status === HotelHold::STATUS_PENDING
            && $hold->expires_at
            && $hold->expires_at->isFuture();
    }

    protected function releaseInventoryForHold(HotelHold $hold): void
    {
        $checkIn = Carbon::parse($hold->check_in)->startOfDay();
        $checkOut = Carbon::parse($hold->check_out)->startOfDay();
        $quote = is_array($hold->quote_json) ? $hold->quote_json : [];
        $lines = is_array($quote['lines'] ?? null) ? $quote['lines'] : [];

        if ($lines === []) {
            $rt = HotelRoomType::query()->find($hold->hotel_room_type_id);
            if ($rt) {
                $this->inventory->releaseHold($rt, $checkIn, $checkOut, 1);
            }

            return;
        }

        foreach ($lines as $line) {
            $rt = HotelRoomType::query()->find((int) ($line['room_type_id'] ?? 0));
            if ($rt) {
                $this->inventory->releaseHold($rt, $checkIn, $checkOut, max(1, (int) ($line['quantity'] ?? 1)));
            }
        }
    }

    /**
     * Ensure module hotel_rooms row is projected as hotel_room_types.code = mod_hr_{id}.
     */
    protected function ensureApiRoomType(HotelRoom $hotelRoom): HotelRoomType
    {
        if (! Schema::hasTable('hotel_room_types')) {
            throw new \RuntimeException('Shared hotel_room_types table is missing');
        }

        $code = 'mod_hr_'.$hotelRoom->id;
        $title = trim((string) ($hotelRoom->name ?? ''));
        if ($title === '') {
            $title = 'Room '.$hotelRoom->id;
        }

        $payload = [
            'title' => $title,
            'max_occupancy' => max(1, (int) ($hotelRoom->max_occupancy ?? 2)),
            'base_price_per_night' => (float) ($hotelRoom->base_price ?? 0),
            'currency' => 'BDT',
            'status' => 1,
        ];
        if (Schema::hasColumn((new HotelRoomType)->getTable(), 'is_active')) {
            $payload['is_active'] = 1;
        }

        return HotelRoomType::query()->updateOrCreate(
            [
                'hotel_id' => (int) $hotelRoom->hotel_id,
                'code' => $code,
            ],
            $payload,
        );
    }

    /**
     * @return list<int>
     */
    protected function normalizeChildrenAges(mixed $raw): array
    {
        if (! is_array($raw)) {
            return [];
        }

        $ages = [];
        foreach ($raw as $age) {
            if (! is_numeric($age)) {
                continue;
            }
            $ages[] = max(0, min(17, (int) $age));
        }

        return $ages;
    }
}
