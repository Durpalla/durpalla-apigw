<?php

namespace App\Services\BoatRental;

use App\Models\Boat;
use App\Models\BoatHold;
use App\Models\BoatStoppage;
use App\Models\BoatTripBid;
use App\Models\BoatTripRequest;
use App\Models\Customer;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

final class BoatTripBidService
{
    public function __construct(
        private readonly BoatInventoryService $inventory,
    ) {}

    /**
     * @return list<array<string, mixed>>
     */
    public function listStoppages(Request $request): array
    {
        if (! Schema::hasTable('boat_stoppages')) {
            return [];
        }

        $q = BoatStoppage::query()->active()->with('city')->orderBy('name');
        if ($request->filled('city_id')) {
            $q->where('city_id', (int) $request->input('city_id'));
        }

        return $q->get()->map(fn (BoatStoppage $s) => [
            'id' => $s->id,
            'name' => $s->name,
            'description' => $s->description,
            'city_id' => $s->city_id,
            'city_name' => $s->city?->name,
            'latitude' => $s->latitude,
            'longitude' => $s->longitude,
        ])->values()->all();
    }

    public function createRequest(?Customer $user, array $input, ?int $agentId = null): BoatTripRequest
    {
        $stoppageIds = array_values(array_unique(array_map('intval', $input['stoppage_ids'] ?? [])));
        if ($stoppageIds === []) {
            throw new \InvalidArgumentException('At least one stoppage is required');
        }

        $startsAt = Carbon::parse((string) ($input['starts_at'] ?? ''));
        $endsAt = Carbon::parse((string) ($input['ends_at'] ?? ''));
        if ($endsAt->lessThanOrEqualTo($startsAt)) {
            throw new \InvalidArgumentException('ends_at must be after starts_at');
        }

        $guests = max(1, (int) ($input['guests'] ?? 1));
        $hours = max(1, (int) config('boat_rental.rfq_expiry_hours', 24));

        return BoatTripRequest::create([
            'user_id' => $user?->id,
            'agent_id' => $agentId,
            'city_id' => isset($input['city_id']) ? (int) $input['city_id'] : null,
            'stoppage_ids' => $stoppageIds,
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
            'guests' => $guests,
            'notes' => $input['notes'] ?? null,
            'status' => BoatTripRequest::STATUS_OPEN,
            'expires_at' => now()->addHours($hours),
        ]);
    }

    /**
     * @return list<BoatTripRequest>
     */
    public function listMyRequests(?Customer $user, ?int $agentId = null): array
    {
        $q = BoatTripRequest::query()->with('city')->orderByDesc('id');
        if ($user) {
            $q->where('user_id', $user->id);
        } elseif ($agentId) {
            $q->where('agent_id', $agentId);
        } else {
            return [];
        }

        return $q->limit(100)->get()->all();
    }

    public function showRequest(int $id, ?Customer $user, ?int $agentId = null, bool $asMerchant = false): ?array
    {
        $request = BoatTripRequest::query()->with(['city', 'bids.boat', 'bids.merchant'])->find($id);
        if (! $request) {
            return null;
        }

        $isOwner = ($user && (int) $request->user_id === (int) $user->id)
            || ($agentId && (int) $request->agent_id === (int) $agentId);

        if (! $asMerchant && ! $isOwner) {
            return null;
        }

        $payload = $this->requestPayload($request);
        if ($isOwner || $asMerchant) {
            $bids = $request->bids
                ->sortBy('amount')
                ->values()
                ->map(fn (BoatTripBid $bid) => $this->bidPayload($bid))
                ->all();
            $payload['bids'] = $bids;
        }

        return $payload;
    }

    public function cancelRequest(int $id, ?Customer $user, ?int $agentId = null): bool
    {
        $q = BoatTripRequest::query()
            ->where('id', $id)
            ->where('status', BoatTripRequest::STATUS_OPEN);
        if ($user) {
            $q->where('user_id', $user->id);
        } elseif ($agentId) {
            $q->where('agent_id', $agentId);
        } else {
            return false;
        }

        $request = $q->first();
        if (! $request) {
            return false;
        }

        $request->update(['status' => BoatTripRequest::STATUS_CANCELLED]);
        BoatTripBid::query()
            ->where('request_id', $request->id)
            ->where('status', BoatTripBid::STATUS_PENDING)
            ->update(['status' => BoatTripBid::STATUS_REJECTED]);

        return true;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listOpenRequestsForMerchant(int $merchantId, ?int $cityId = null): array
    {
        $this->expireOpenRequests();

        $cityIds = Boat::query()
            ->where('merchant_id', $merchantId)
            ->whereNotNull('city_id')
            ->pluck('city_id')
            ->unique()
            ->filter()
            ->values()
            ->all();

        $q = BoatTripRequest::query()
            ->open()
            ->where(function ($inner) {
                $inner->whereNull('expires_at')->orWhere('expires_at', '>', now());
            })
            ->with('city')
            ->orderByDesc('id');

        if ($cityId) {
            $q->where('city_id', $cityId);
        } elseif ($cityIds !== []) {
            $q->where(function ($inner) use ($cityIds) {
                $inner->whereIn('city_id', $cityIds)->orWhereNull('city_id');
            });
        }

        return $q->limit(100)->get()->map(fn (BoatTripRequest $r) => $this->requestPayload($r))->values()->all();
    }

    public function placeBid(int $merchantId, int $requestId, array $input): BoatTripBid
    {
        $this->expireOpenRequests();

        $request = BoatTripRequest::query()->findOrFail($requestId);
        if ($request->status !== BoatTripRequest::STATUS_OPEN
            || ($request->expires_at && now()->greaterThan($request->expires_at))) {
            throw new \RuntimeException('Trip request is not open');
        }

        $boat = Boat::query()
            ->where('merchant_id', $merchantId)
            ->active()
            ->findOrFail((int) ($input['boat_id'] ?? 0));

        $guests = (int) $request->guests;
        $this->inventory->assertCapacity((int) $boat->capacity_max, $guests);
        $this->inventory->assertAvailable(
            (int) $boat->id,
            Carbon::parse($request->starts_at),
            Carbon::parse($request->ends_at),
        );

        $amount = round((float) ($input['amount'] ?? 0), 2);
        $minBid = (float) config('boat_rental.min_bid_amount', 1);
        if ($amount < $minBid) {
            throw new \InvalidArgumentException('Bid amount is below minimum');
        }

        $bid = BoatTripBid::query()
            ->where('request_id', $request->id)
            ->where('boat_id', $boat->id)
            ->first();

        if ($bid) {
            if ($bid->status === BoatTripBid::STATUS_ACCEPTED) {
                throw new \RuntimeException('Bid already accepted');
            }
            $bid->update([
                'merchant_id' => $merchantId,
                'amount' => $amount,
                'message' => $input['message'] ?? $bid->message,
                'status' => BoatTripBid::STATUS_PENDING,
            ]);

            return $bid->fresh(['boat', 'merchant']);
        }

        return BoatTripBid::create([
            'request_id' => $request->id,
            'merchant_id' => $merchantId,
            'boat_id' => $boat->id,
            'amount' => $amount,
            'message' => $input['message'] ?? null,
            'status' => BoatTripBid::STATUS_PENDING,
        ])->load(['boat', 'merchant']);
    }

    public function updateBid(int $merchantId, int $bidId, array $input): BoatTripBid
    {
        $bid = BoatTripBid::query()
            ->where('merchant_id', $merchantId)
            ->where('status', BoatTripBid::STATUS_PENDING)
            ->findOrFail($bidId);

        $request = BoatTripRequest::query()->findOrFail($bid->request_id);
        if ($request->status !== BoatTripRequest::STATUS_OPEN) {
            throw new \RuntimeException('Trip request is not open');
        }

        $data = [];
        if (isset($input['amount'])) {
            $amount = round((float) $input['amount'], 2);
            if ($amount < (float) config('boat_rental.min_bid_amount', 1)) {
                throw new \InvalidArgumentException('Bid amount is below minimum');
            }
            $data['amount'] = $amount;
        }
        if (array_key_exists('message', $input)) {
            $data['message'] = $input['message'];
        }
        if ($data !== []) {
            $bid->update($data);
        }

        return $bid->fresh(['boat', 'merchant']);
    }

    public function withdrawBid(int $merchantId, int $bidId): bool
    {
        $bid = BoatTripBid::query()
            ->where('merchant_id', $merchantId)
            ->where('status', BoatTripBid::STATUS_PENDING)
            ->find($bidId);

        if (! $bid) {
            return false;
        }

        $bid->update(['status' => BoatTripBid::STATUS_WITHDRAWN]);

        return true;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listBidsForRequest(int $requestId, ?Customer $user, ?int $agentId = null): array
    {
        $request = BoatTripRequest::query()->findOrFail($requestId);
        $isOwner = ($user && (int) $request->user_id === (int) $user->id)
            || ($agentId && (int) $request->agent_id === (int) $agentId);
        if (! $isOwner) {
            throw new \RuntimeException('Not authorized');
        }

        return BoatTripBid::query()
            ->where('request_id', $requestId)
            ->with(['boat', 'merchant'])
            ->orderBy('amount')
            ->get()
            ->map(fn (BoatTripBid $bid) => $this->bidPayload($bid))
            ->values()
            ->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listMerchantBids(int $merchantId): array
    {
        return BoatTripBid::query()
            ->where('merchant_id', $merchantId)
            ->with(['boat', 'request.city'])
            ->orderByDesc('id')
            ->limit(100)
            ->get()
            ->map(fn (BoatTripBid $bid) => $this->bidPayload($bid) + [
                'request' => $bid->request ? $this->requestPayload($bid->request) : null,
            ])
            ->values()
            ->all();
    }

    public function acceptBid(
        ?Customer $user,
        int $bidId,
        ?int $agentId = null,
        string $idempotencyKey = '',
    ): BoatHold {
        return DB::transaction(function () use ($user, $bidId, $agentId, $idempotencyKey) {
            if ($idempotencyKey !== '') {
                $existingQ = BoatHold::query()->where('idempotency_key', $idempotencyKey);
                if ($user) {
                    $existingQ->where('user_id', $user->id);
                } elseif ($agentId) {
                    $existingQ->where('agent_id', $agentId);
                }
                $existing = $existingQ->first();
                if ($existing) {
                    return $existing;
                }
            }

            $bid = BoatTripBid::query()->lockForUpdate()->with('boat')->findOrFail($bidId);
            $request = BoatTripRequest::query()->lockForUpdate()->findOrFail($bid->request_id);

            $isOwner = ($user && (int) $request->user_id === (int) $user->id)
                || ($agentId && (int) $request->agent_id === (int) $agentId);
            if (! $isOwner) {
                throw new \RuntimeException('Not authorized');
            }

            if ($request->status !== BoatTripRequest::STATUS_OPEN
                || ($request->expires_at && now()->greaterThan($request->expires_at))) {
                throw new \RuntimeException('Trip request is not open');
            }
            if ($bid->status !== BoatTripBid::STATUS_PENDING) {
                throw new \RuntimeException('Bid is not pending');
            }

            $boat = $bid->boat;
            if (! $boat || (int) $boat->status !== 1) {
                throw new \RuntimeException('Boat is not available');
            }

            $startsAt = Carbon::parse($request->starts_at);
            $endsAt = Carbon::parse($request->ends_at);
            $this->inventory->assertCapacity((int) $boat->capacity_max, (int) $request->guests);
            $this->inventory->assertAvailable((int) $boat->id, $startsAt, $endsAt);

            BoatTripBid::query()
                ->where('request_id', $request->id)
                ->where('id', '!=', $bid->id)
                ->where('status', BoatTripBid::STATUS_PENDING)
                ->update(['status' => BoatTripBid::STATUS_REJECTED]);

            $bid->update(['status' => BoatTripBid::STATUS_ACCEPTED]);
            $request->update([
                'status' => BoatTripRequest::STATUS_AWARDED,
                'accepted_bid_id' => $bid->id,
            ]);

            $stoppages = $this->stoppagesFromIds($request->stoppage_ids ?? []);
            $ttl = max(5, (int) config('boat_rental.hold_ttl_minutes', 15));
            $amount = round((float) $bid->amount, 2);

            return BoatHold::create([
                'boat_id' => $boat->id,
                'user_id' => $user?->id ?? $request->user_id,
                'agent_id' => $agentId ?? $request->agent_id,
                'rental_mode' => 'bid',
                'pricing_source' => 'bid',
                'starts_at' => $startsAt,
                'ends_at' => $endsAt,
                'trip_request_id' => $request->id,
                'bid_id' => $bid->id,
                'guests' => (int) $request->guests,
                'unit_price' => $amount,
                'total_price' => $amount,
                'units' => 1,
                'stoppages_json' => $stoppages,
                'status' => BoatHold::STATUS_PENDING,
                'expires_at' => now()->addMinutes($ttl),
                'idempotency_key' => $idempotencyKey !== '' ? $idempotencyKey : null,
            ]);
        });
    }

    public function expireOpenRequests(): int
    {
        $ids = BoatTripRequest::query()
            ->open()
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', now())
            ->pluck('id');

        if ($ids->isEmpty()) {
            return 0;
        }

        BoatTripRequest::query()->whereIn('id', $ids)->update(['status' => BoatTripRequest::STATUS_EXPIRED]);
        BoatTripBid::query()
            ->whereIn('request_id', $ids)
            ->where('status', BoatTripBid::STATUS_PENDING)
            ->update(['status' => BoatTripBid::STATUS_REJECTED]);

        return $ids->count();
    }

    /**
     * @return array<string, mixed>
     */
    public function requestPayload(BoatTripRequest $request): array
    {
        return [
            'id' => $request->id,
            'user_id' => $request->user_id,
            'agent_id' => $request->agent_id,
            'city_id' => $request->city_id,
            'city_name' => $request->city?->name,
            'stoppage_ids' => $request->stoppage_ids ?? [],
            'starts_at' => $request->starts_at?->toIso8601String(),
            'ends_at' => $request->ends_at?->toIso8601String(),
            'guests' => (int) $request->guests,
            'notes' => $request->notes,
            'status' => $request->status,
            'expires_at' => $request->expires_at?->toIso8601String(),
            'accepted_bid_id' => $request->accepted_bid_id,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function bidPayload(BoatTripBid $bid): array
    {
        return [
            'id' => $bid->id,
            'request_id' => (int) $bid->request_id,
            'merchant_id' => (int) $bid->merchant_id,
            'boat_id' => (int) $bid->boat_id,
            'boat_title' => $bid->boat?->title,
            'amount' => (float) $bid->amount,
            'message' => $bid->message,
            'status' => $bid->status,
        ];
    }

    /**
     * @param  list<int|string>  $ids
     * @return list<array<string, mixed>>|null
     */
    private function stoppagesFromIds(array $ids): ?array
    {
        if ($ids === []) {
            return null;
        }

        $stoppages = BoatStoppage::query()->whereIn('id', $ids)->get()->keyBy('id');
        $out = [];
        foreach ($ids as $i => $id) {
            $s = $stoppages->get((int) $id);
            if (! $s) {
                continue;
            }
            $out[] = [
                'stoppage_id' => (int) $s->id,
                'name' => $s->name,
                'sort_order' => $i,
                'latitude' => $s->latitude,
                'longitude' => $s->longitude,
            ];
        }

        return $out !== [] ? $out : null;
    }
}
