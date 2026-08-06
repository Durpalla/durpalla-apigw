<?php

namespace App\Services;

use App\Constants\AppConst;
use App\Models\AccountStatement;
use App\Models\Agent;
use App\Models\AgentBalance;
use App\Models\AgentCommission;
use App\Models\AgentCommissionAccrual;
use App\Models\AgentIncentive;
use App\Models\Booking;
use App\Models\BookingItem;
use App\Models\Customer;
use App\Models\Merchant;
use App\Models\MerchantStaff;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AgentJourneyCommissionService
{
    public function __construct(
        private readonly AccountStatementService $statements,
        private readonly AgentReferralAttributionService $attribution,
        private readonly FinancialLedgerService $ledger,
    ) {}

    public function reconcile(int $limit = 200): array
    {
        $stats = ['accrued' => 0, 'settled' => 0, 'voided' => 0, 'reversed' => 0];

        Booking::query()->useWritePdo()
            ->whereIn('status', [AppConst::BOOKING_COMPLETE, 'ACTIVE', 'active', 'CONFIRMED', 'confirmed'])
            ->whereNull('commission_accruals_checked_at')
            ->orderBy('id')->limit($limit)->pluck('id')
            ->each(function (int $id) use (&$stats) {
                $stats['accrued'] += $this->accrueBooking($id);
            });

        AgentCommissionAccrual::query()
            ->where('status', AgentCommissionAccrual::STATUS_PENDING)
            ->where('eligible_at', '<=', now())
            ->orderBy('eligible_at')->orderBy('id')->limit($limit)->pluck('id')
            ->each(function (int $id) use (&$stats) {
                if ($result = $this->processAccrual($id)) {
                    $stats[$result]++;
                }
            });

        collect($this->cancellationCandidateIds($limit))->each(function (int $id) use (&$stats) {
            if ($result = $this->processAccrual($id)) {
                $stats[$result]++;
            }
        });

        return $stats;
    }

    public function creditDueItems(int $limit = 200): int
    {
        return $this->reconcile($limit)['settled'];
    }

    /**
     * Find bookings that were marked commission-checked but are missing expected
     * accruals (zero rows, or dual booker/referrer gap), clear the lock, and re-accrue.
     *
     * Cron should pass a short lookback (default 1 hour) so each hourly run only
     * inspects recent bookings — not the full history. Older claims use admin repair.
     *
     * @return array{candidates: int, repaired: int, accrued: int, booking_ids: list<int>, hours: int}
     */
    public function repairMissingAccruals(int $limit = 100, bool $dryRun = false, int $hours = 1): array
    {
        $hours = max(1, $hours);
        $ids = $this->missingCommissionBookingIds($limit, $hours);
        $stats = [
            'candidates' => count($ids),
            'repaired' => 0,
            'accrued' => 0,
            'booking_ids' => $ids,
            'hours' => $hours,
        ];

        if ($dryRun || $ids === []) {
            return $stats;
        }

        foreach ($ids as $bookingId) {
            $created = $this->reinitiateBookingCommission($bookingId);
            $stats['repaired']++;
            $stats['accrued'] += $created;
        }

        return $stats;
    }

    /**
     * Force clear the checked lock and re-accrue a single booking (admin / repair).
     */
    public function reinitiateBookingCommission(int $bookingId): int
    {
        Booking::query()->useWritePdo()
            ->where('id', $bookingId)
            ->update(['commission_accruals_checked_at' => null]);

        return $this->accrueBooking($bookingId);
    }

    /**
     * Bookings in the lookback window that look commissionable but lack expected accruals.
     *
     * @return list<int>
     */
    public function missingCommissionBookingIds(int $limit = 100, int $hours = 1): array
    {
        $hours = max(1, $hours);
        $since = now()->subHours($hours);
        $statuses = [AppConst::BOOKING_COMPLETE, 'ACTIVE', 'active', 'CONFIRMED', 'confirmed'];
        $open = [AgentCommissionAccrual::STATUS_PENDING, AgentCommissionAccrual::STATUS_SETTLED];

        $base = Booking::query()->useWritePdo()
            ->whereIn('status', $statuses)
            ->whereNull('deleted_at')
            ->whereNotNull('commission_accruals_checked_at')
            ->where(function ($q) use ($since) {
                $q->where('commission_accruals_checked_at', '>=', $since)
                    ->orWhere('created_at', '>=', $since);
            })
            ->where(function ($q) {
                // Agent counter bookings OR Durpalla customer bookings of referred inventory.
                $q->where(function ($agent) {
                    $agent->where('booked_by_type', Agent::class)
                        ->where('booked_by_id', '>', 0);
                })->orWhere(function ($referral) {
                    $referral->where('referring_agent_id', '>', 0)
                        ->where(function ($booker) {
                            $booker->whereNull('booked_by_type')
                                ->orWhere('booked_by_type', Customer::class)
                                ->orWhere('booked_by_type', Agent::class);
                        });
                });
            });
        $this->applyExcludeMerchantSideBookingConstraints($base);
        $columns = ['id', 'booked_by_type', 'booked_by_id', 'referring_agent_id', 'charge_total'];
        if (Schema::hasColumn('bookings', 'booking_party')) {
            $columns[] = 'booking_party';
        }
        if (Schema::hasColumn('bookings', 'platform')) {
            $columns[] = 'platform';
        }
        $base = $base
            ->orderByDesc('commission_accruals_checked_at')
            ->limit(max(1, $limit))
            ->get($columns);

        $missing = [];
        foreach ($base as $booking) {
            if (count($missing) >= $limit) {
                break;
            }
            if ($this->isMerchantSideBooking($booking) || ! $this->isAgentCommissionScopeBooking($booking)) {
                continue;
            }
            if (! $this->bookingLooksCommissionable($booking)) {
                continue;
            }

            $this->attribution->attribute($booking);
            $booking->refresh();

            $expected = $this->expectedBeneficiaries($booking);
            if ($expected === []) {
                continue;
            }

            $payable = [];
            foreach ($expected as $beneficiary) {
                $incentive = AgentIncentive::query()
                    ->where('agent_id', $beneficiary['agent_id'])
                    ->first();
                if ($incentive && (float) $incentive->incentive > 0) {
                    $payable[] = $beneficiary;
                }
            }
            if ($payable === []) {
                continue;
            }

            $existing = AgentCommissionAccrual::query()
                ->where('booking_id', $booking->id)
                ->whereIn('status', $open)
                ->get(['agent_id', 'kind']);

            foreach ($payable as $beneficiary) {
                $has = $existing->contains(function ($row) use ($beneficiary) {
                    return (int) $row->agent_id === (int) $beneficiary['agent_id']
                        && (string) $row->kind === (string) $beneficiary['kind'];
                });
                if (! $has) {
                    $missing[] = (int) $booking->id;
                    break;
                }
            }
        }

        return $missing;
    }

    public function pendingAmountForAgent(int $agentId): float
    {
        return (float) AgentCommissionAccrual::query()
            ->where('agent_id', $agentId)->pending()->sum('amount');
    }

    public function pendingItemsForAgent(int $agentId)
    {
        return AgentCommissionAccrual::query()
            ->with(['booking', 'bookingItem.vehicle'])
            ->where('agent_id', $agentId)->pending()->orderByDesc('id')->get();
    }

    public function accrueBooking(int|Booking $booking): int
    {
        $bookingId = $booking instanceof Booking ? (int) $booking->id : $booking;

        return DB::transaction(function () use ($bookingId) {
            $booking = Booking::query()->useWritePdo()->lockForUpdate()->find($bookingId);
            if (! $booking || $booking->commission_accruals_checked_at || $this->bookingCancelled($booking)) {
                return 0;
            }

            // Merchant desk / merchant-party bookings never earn agent commission.
            if ($this->isMerchantSideBooking($booking)) {
                $this->markBookingChecked($booking);

                return 0;
            }

            $this->attribution->attribute($booking);
            $booking->refresh();

            // Only agent counter bookings and Durpalla customer bookings of referred inventory.
            if (! $this->isAgentCommissionScopeBooking($booking)) {
                $this->markBookingChecked($booking);

                return 0;
            }

            $bookerId = $this->bookedByAgent($booking) ? (int) $booking->booked_by_id : 0;
            $referrerId = (int) ($booking->referring_agent_id ?? 0);

            // Dual commission:
            // - Booking agent gets kind=booking unless they are also the referrer
            // - Referring agent always gets kind=referral (including self-book on own referral)
            $beneficiaries = [];
            if ($bookerId > 0 && $bookerId !== $referrerId) {
                $beneficiaries[] = ['agent_id' => $bookerId, 'kind' => 'booking'];
            }
            if ($referrerId > 0) {
                $beneficiaries[] = ['agent_id' => $referrerId, 'kind' => 'referral'];
            }

            if ($beneficiaries === []) {
                $this->markBookingChecked($booking);

                return 0;
            }

            $count = 0;
            $hadPayableIncentive = false;
            foreach ($beneficiaries as $beneficiary) {
                $incentive = AgentIncentive::query()
                    ->where('agent_id', $beneficiary['agent_id'])
                    ->first();
                if (! $incentive || (float) $incentive->incentive <= 0) {
                    continue;
                }
                $hadPayableIncentive = true;
                $count += $this->accrueTransport($booking, $beneficiary['agent_id'], $beneficiary['kind'], $incentive)
                    + $this->accrueHotelReservations($booking, $beneficiary['agent_id'], $beneficiary['kind'], $incentive)
                    + $this->accrueHotelItems($booking, $beneficiary['agent_id'], $beneficiary['kind'], $incentive);
            }

            // Leave unchecked only when transport lines exist but departure is not ready yet.
            if ($count > 0 || ! $hadPayableIncentive || ! $this->hasUnreadyTransportLines($booking)) {
                $this->markBookingChecked($booking);
            }

            return $count;
        }, 3);
    }

    private function accrueTransport(Booking $booking, int $agentId, string $kind, AgentIncentive $incentive): int
    {
        $count = 0;
        foreach ($booking->bookingItems()->where('status', AppConst::BOOKING_ITEM_ACTIVE)->get() as $item) {
            if (($item->item_type ?? 'transport') === 'hotel') {
                continue;
            }
            $leavingAt = $item->trip_id
                ? DB::table('vehicle_schedules')->where('id', $item->trip_id)->value('leaving_at')
                : $item->trip_date;
            if (! $leavingAt) {
                continue;
            }
            $base = $item->charge_type === 'percent'
                ? (float) $item->price * (float) $item->charge_amount / 100
                : (float) $item->charge_amount;
            $count += $this->createAccrual(
                $booking, $agentId, $kind, 'transport', 'booking_item', (int) $item->id,
                $base, Carbon::parse($leavingAt), $incentive, (int) $item->id
            );
        }

        return $count;
    }

    private function accrueHotelReservations(Booking $booking, int $agentId, string $kind, AgentIncentive $incentive): int
    {
        if (! Schema::hasTable('hotel_reservations')) {
            return 0;
        }
        $count = 0;
        foreach (DB::table('hotel_reservations')->where('booking_id', $booking->id)->get() as $row) {
            $quote = json_decode((string) ($row->quote_json ?? ''), true) ?: [];
            $count += $this->createAccrual(
                $booking, $agentId, $kind, 'hotel', 'hotel_reservation', (int) $row->id,
                (float) ($quote['charge_amount'] ?? $booking->charge_total ?? 0),
                $this->hotelEligibleAt((string) $row->check_out, (int) $row->hotel_id),
                $incentive
            );
        }

        return $count;
    }

    private function accrueHotelItems(Booking $booking, int $agentId, string $kind, AgentIncentive $incentive): int
    {
        if (! Schema::hasTable('booking_hotel_items')) {
            return 0;
        }
        if (Schema::hasTable('hotel_reservations')
            && DB::table('hotel_reservations')->where('booking_id', $booking->id)->exists()) {
            return 0;
        }
        $rows = DB::table('booking_hotel_items')->where('booking_id', $booking->id)->get();
        $total = max(0.01, (float) $rows->sum('total_price'));
        $count = 0;
        foreach ($rows as $row) {
            $count += $this->createAccrual(
                $booking, $agentId, $kind, 'hotel', 'booking_hotel_item', (int) $row->id,
                (float) $booking->charge_total * ((float) $row->total_price / $total),
                $this->hotelEligibleAt((string) $row->check_out_date, (int) $row->hotel_id),
                $incentive
            );
        }

        return $count;
    }

    private function createAccrual(
        Booking $booking,
        int $agentId,
        string $kind,
        string $serviceType,
        string $sourceType,
        int $sourceId,
        float $base,
        Carbon $eligibleAt,
        AgentIncentive $incentive,
        ?int $bookingItemId = null,
    ): int {
        $base = round(max(0, $base), 2);
        $rate = (float) $incentive->incentive;
        $type = $incentive->incentive_type === 'fixed' ? 'fixed' : 'percent';
        $amount = round($type === 'fixed' ? $rate : $base * $rate / 100, 2);
        if ($amount <= 0) {
            return 0;
        }
        $row = AgentCommissionAccrual::query()->firstOrCreate(
            ['source_key' => "{$agentId}:{$kind}:{$serviceType}:{$sourceType}:{$sourceId}"],
            [
                'agent_id' => $agentId,
                'booking_id' => $booking->id,
                'booking_item_id' => $bookingItemId,
                'source_type' => $sourceType,
                'source_id' => $sourceId,
                'kind' => $kind,
                'service_type' => $serviceType,
                'base_amount' => $base,
                'rate' => $rate,
                'incentive_type' => $type,
                'amount' => $amount,
                'eligible_at' => $eligibleAt,
                'status' => AgentCommissionAccrual::STATUS_PENDING,
                'meta' => ['incentive_id' => (int) $incentive->id],
            ]
        );

        if ($row->wasRecentlyCreated) {
            $this->ledger->recordAgentCommissionAccrued(
                (int) $agentId,
                $amount,
                (int) $row->id,
                (int) $booking->id,
                $bookingItemId
            );
        }

        return $row->wasRecentlyCreated ? 1 : 0;
    }

    private function processAccrual(int $id): ?string
    {
        return DB::transaction(function () use ($id) {
            $accrual = AgentCommissionAccrual::query()->lockForUpdate()->find($id);
            if (! $accrual || ! in_array($accrual->status, ['pending', 'settled'], true)) {
                return null;
            }
            if ($this->accrualCancelled($accrual)) {
                return $accrual->status === AgentCommissionAccrual::STATUS_PENDING
                    ? $this->void($accrual) : $this->reverse($accrual);
            }
            if ($accrual->status !== AgentCommissionAccrual::STATUS_PENDING || $accrual->eligible_at->isFuture()) {
                return null;
            }

            Agent::query()->lockForUpdate()->find($accrual->agent_id);
            $commission = AgentCommission::query()->firstOrCreate(
                ['accrual_id' => $accrual->id, 'type' => 'credit'],
                [
                    'user_id' => $accrual->agent_id,
                    'booking_item_id' => $accrual->booking_item_id,
                    'purpose' => 'commission',
                    'commission_date' => now()->toDateString(),
                    'total_sale' => $accrual->base_amount,
                    'amount' => $accrual->amount,
                ]
            );
            if (! $commission->wasRecentlyCreated && $accrual->commission_id) {
                return null;
            }
            $this->changeBalance($accrual, (float) $accrual->amount, false);
            $accrual->update([
                'status' => AgentCommissionAccrual::STATUS_SETTLED,
                'commission_id' => $commission->id,
                'settled_at' => now(),
            ]);
            $this->ledger->recordAgentCommissionSettled(
                (int) $accrual->agent_id,
                (float) $accrual->amount,
                (int) $accrual->id,
                (int) $accrual->booking_id,
                $accrual->booking_item_id ? (int) $accrual->booking_item_id : null
            );

            return 'settled';
        }, 3);
    }

    private function void(AgentCommissionAccrual $accrual): string
    {
        $accrual->update(['status' => AgentCommissionAccrual::STATUS_VOID, 'voided_at' => now()]);
        $this->ledger->recordAgentCommissionVoided(
            (int) $accrual->agent_id,
            (float) $accrual->amount,
            (int) $accrual->id
        );

        return 'voided';
    }

    private function reverse(AgentCommissionAccrual $accrual): string
    {
        Agent::query()->lockForUpdate()->find($accrual->agent_id);
        $commission = AgentCommission::query()->firstOrCreate(
            ['accrual_id' => $accrual->id, 'type' => 'debit'],
            [
                'user_id' => $accrual->agent_id,
                'booking_item_id' => $accrual->booking_item_id,
                'purpose' => 'cancellation',
                'commission_date' => now()->toDateString(),
                'total_sale' => $accrual->base_amount,
                'amount' => $accrual->amount,
            ]
        );
        if ($commission->wasRecentlyCreated) {
            $this->changeBalance($accrual, (float) $accrual->amount, true);
            $this->ledger->recordAgentCommissionReversed(
                (int) $accrual->agent_id,
                (float) $accrual->amount,
                (int) $accrual->id
            );
        }
        $accrual->update([
            'status' => AgentCommissionAccrual::STATUS_REVERSED,
            'reversal_commission_id' => $commission->id,
            'reversed_at' => now(),
        ]);

        return 'reversed';
    }

    private function changeBalance(AgentCommissionAccrual $accrual, float $amount, bool $debit): void
    {
        $balance = AgentBalance::query()->lockForUpdate()->where('user_id', $accrual->agent_id)->first();
        if (! $balance) {
            $balance = AgentBalance::query()->create(['user_id' => $accrual->agent_id, 'balance' => 0]);
        }
        $before = (float) $balance->balance;
        $after = round($before + ($debit ? -$amount : $amount), 2);
        $balance->update(['balance' => $after]);
        Cache::forget('my_balance_'.$accrual->agent_id);

        $this->statements->record(
            AccountStatement::ACCOUNT_AGENT,
            (int) $accrual->agent_id,
            $debit ? AccountStatement::DIRECTION_DEBIT : AccountStatement::DIRECTION_CREDIT,
            $amount, $before, $after, 'commission', 'accrual:'.$accrual->id,
            ($debit ? 'Commission reversed' : ucfirst($accrual->kind).' commission credited').' for booking #'.$accrual->booking_id,
            ['accrual_id' => $accrual->id, 'kind' => $accrual->kind, 'service_type' => $accrual->service_type],
            'agent:commission:accrual:'.$accrual->id.($debit ? ':debit' : ':credit')
        );
    }

    private function accrualCancelled(AgentCommissionAccrual $accrual): bool
    {
        $booking = Booking::withTrashed()->find($accrual->booking_id);
        if (! $booking || $this->bookingCancelled($booking)) {
            return true;
        }
        if ($accrual->source_type === 'booking_item') {
            $item = BookingItem::query()->find($accrual->source_id);
            if (! $item || (int) $item->status !== AppConst::BOOKING_ITEM_ACTIVE) {
                return true;
            }

            return Schema::hasTable('booking_cancellation_items')
                && DB::table('booking_cancellation_items')->where('booking_item_id', $item->id)
                    ->whereIn('status', [1, 2, 3])->exists();
        }
        if ($accrual->source_type === 'hotel_reservation') {
            $status = DB::table('hotel_reservations')->where('id', $accrual->source_id)->value('status');

            return ! $status || in_array(strtolower((string) $status), ['cancelled', 'canceled', 'failed', 'refunded'], true);
        }
        if ($accrual->source_type === 'booking_hotel_item') {
            return ! Schema::hasTable('booking_hotel_items')
                || ! DB::table('booking_hotel_items')->where('id', $accrual->source_id)->exists();
        }

        return false;
    }

    /** @return list<int> */
    private function cancellationCandidateIds(int $limit): array
    {
        $open = [AgentCommissionAccrual::STATUS_PENDING, AgentCommissionAccrual::STATUS_SETTLED];
        $ids = DB::table('agent_commission_accruals as a')->join('bookings as b', 'b.id', '=', 'a.booking_id')
            ->whereIn('a.status', $open)
            ->where(function ($q) {
                $q->whereIn(DB::raw('UPPER(b.status)'), ['CANCELLED', 'CANCELED', 'FAILED', 'REJECTED', 'REFUNDED'])
                    ->orWhereNotNull('b.deleted_at');
            })->limit($limit)->pluck('a.id');

        $sources = [
            ['booking_items', 'i', 'booking_item', 'status'],
            ['hotel_reservations', 'r', 'hotel_reservation', 'status'],
            ['booking_hotel_items', 'h', 'booking_hotel_item', null],
        ];
        foreach ($sources as [$table, $alias, $type, $statusColumn]) {
            if (! Schema::hasTable($table)) {
                continue;
            }
            $query = DB::table('agent_commission_accruals as a')
                ->leftJoin("{$table} as {$alias}", "{$alias}.id", '=', 'a.source_id')
                ->whereIn('a.status', $open)->where('a.source_type', $type)
                ->where(function ($q) use ($alias, $type, $statusColumn) {
                    $q->whereNull("{$alias}.id");
                    if ($type === 'booking_item') {
                        $q->orWhere("{$alias}.{$statusColumn}", '!=', AppConst::BOOKING_ITEM_ACTIVE);
                    } elseif ($type === 'hotel_reservation') {
                        $q->orWhereIn(DB::raw("LOWER({$alias}.{$statusColumn})"), ['cancelled', 'canceled', 'failed', 'refunded']);
                    }
                });
            $ids = $ids->merge($query->limit($limit)->pluck('a.id'));
        }
        if (Schema::hasTable('booking_cancellation_items')) {
            $ids = $ids->merge(DB::table('agent_commission_accruals as a')
                ->join('booking_cancellation_items as c', 'c.booking_item_id', '=', 'a.source_id')
                ->whereIn('a.status', $open)->where('a.source_type', 'booking_item')
                ->whereIn('c.status', [1, 2, 3])->limit($limit)->pluck('a.id'));
        }

        return $ids->unique()->take($limit)->map(fn ($id) => (int) $id)->values()->all();
    }

    private function bookingCancelled(Booking $booking): bool
    {
        return $booking->trashed()
            || in_array(strtoupper((string) $booking->status), ['CANCELLED', 'CANCELED', 'FAILED', 'REJECTED', 'REFUNDED'], true);
    }

    private function bookedByAgent(Booking $booking): bool
    {
        return is_string($booking->booked_by_type)
            && is_a($booking->booked_by_type, Agent::class, true)
            && (int) $booking->booked_by_id > 0;
    }

    /**
     * Merchant desk / merchant-party bookings are out of scope for agent commission.
     */
    private function isMerchantSideBooking(Booking $booking): bool
    {
        if (Schema::hasColumn('bookings', 'booking_party')
            && strtolower((string) ($booking->booking_party ?? '')) === 'merchant') {
            return true;
        }
        if (Schema::hasColumn('bookings', 'platform')
            && strtolower((string) ($booking->platform ?? '')) === 'merchant_desk') {
            return true;
        }
        $type = (string) ($booking->booked_by_type ?? '');
        if ($type === '') {
            return false;
        }

        return is_a($type, Merchant::class, true) || is_a($type, MerchantStaff::class, true);
    }

    /**
     * Agent commission applies to:
     * - agent counter bookings, and
     * - Durpalla customer bookings of agent-referred inventory.
     */
    private function isAgentCommissionScopeBooking(Booking $booking): bool
    {
        if ($this->isMerchantSideBooking($booking)) {
            return false;
        }
        if ($this->bookedByAgent($booking)) {
            return true;
        }
        if ((int) ($booking->referring_agent_id ?? 0) <= 0) {
            return false;
        }
        if (Schema::hasColumn('bookings', 'booking_party')) {
            $party = strtolower((string) ($booking->booking_party ?? ''));
            if ($party !== '' && $party !== 'durpalla' && $party !== AppConst::OWNER) {
                return false;
            }
        }

        return true;
    }

    /**
     * SQL constraints that drop merchant desk / merchant-party bookings.
     */
    private function applyExcludeMerchantSideBookingConstraints($query): void
    {
        if (Schema::hasColumn('bookings', 'booking_party')) {
            $query->where(function ($q) {
                $q->whereNull('booking_party')
                    ->orWhere('booking_party', '!=', 'merchant');
            });
        }
        if (Schema::hasColumn('bookings', 'platform')) {
            $query->where(function ($q) {
                $q->whereNull('platform')
                    ->orWhere('platform', '!=', 'merchant_desk');
            });
        }
        $query->where(function ($q) {
            $q->whereNull('booked_by_type')
                ->orWhereNotIn('booked_by_type', [Merchant::class, MerchantStaff::class]);
        });
    }

    /**
     * @return list<array{agent_id: int, kind: string}>
     */
    private function expectedBeneficiaries(Booking $booking): array
    {
        $bookerId = $this->bookedByAgent($booking) ? (int) $booking->booked_by_id : 0;
        $referrerId = (int) ($booking->referring_agent_id ?? 0);
        $beneficiaries = [];
        if ($bookerId > 0 && $bookerId !== $referrerId) {
            $beneficiaries[] = ['agent_id' => $bookerId, 'kind' => 'booking'];
        }
        if ($referrerId > 0) {
            $beneficiaries[] = ['agent_id' => $referrerId, 'kind' => 'referral'];
        }

        return $beneficiaries;
    }

    private function bookingLooksCommissionable(Booking $booking): bool
    {
        if ((float) ($booking->charge_total ?? 0) > 0) {
            return true;
        }
        if ($booking->bookingItems()->where('status', AppConst::BOOKING_ITEM_ACTIVE)
            ->where(function ($q) {
                $q->whereNull('item_type')->orWhere('item_type', '!=', 'hotel');
            })
            ->where(function ($q) {
                $q->where('charge_amount', '>', 0)->orWhere('price', '>', 0);
            })
            ->exists()) {
            return true;
        }
        if (Schema::hasTable('hotel_reservations')
            && DB::table('hotel_reservations')->where('booking_id', $booking->id)->exists()) {
            return true;
        }
        if (Schema::hasTable('booking_hotel_items')
            && DB::table('booking_hotel_items')->where('booking_id', $booking->id)->exists()) {
            return true;
        }

        return false;
    }

    /**
     * True when an active transport line cannot yet be accrued (no departure time).
     */
    private function hasUnreadyTransportLines(Booking $booking): bool
    {
        foreach ($booking->bookingItems()->where('status', AppConst::BOOKING_ITEM_ACTIVE)->get() as $item) {
            if (($item->item_type ?? 'transport') === 'hotel') {
                continue;
            }
            $leavingAt = $item->trip_id
                ? DB::table('vehicle_schedules')->where('id', $item->trip_id)->value('leaving_at')
                : $item->trip_date;
            if (! $leavingAt) {
                return true;
            }
        }

        return false;
    }

    private function hotelEligibleAt(string $checkOut, int $hotelId): Carbon
    {
        $date = Carbon::parse($checkOut);
        if (strlen(trim($checkOut)) > 10) {
            return $date;
        }
        $time = Schema::hasColumn('hotels', 'check_out_time')
            ? DB::table('hotels')->where('id', $hotelId)->value('check_out_time') : null;

        return $time ? Carbon::parse($date->toDateString().' '.$time) : $date->endOfDay();
    }

    private function markBookingChecked(Booking $booking): void
    {
        $booking->forceFill(['commission_accruals_checked_at' => now()])->saveQuietly();
    }
}
