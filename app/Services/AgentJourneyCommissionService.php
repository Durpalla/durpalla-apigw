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

            if (! $this->bookedByAgent($booking)) {
                $this->attribution->attribute($booking);
                $booking->refresh();
            }
            $isAgentBooking = $this->bookedByAgent($booking);
            $agentId = $isAgentBooking
                ? (int) $booking->booked_by_id
                : (int) ($booking->referring_agent_id ?? 0);
            if ($agentId <= 0) {
                $this->markBookingChecked($booking);

                return 0;
            }

            $incentive = AgentIncentive::query()->where('agent_id', $agentId)->first();
            if (! $incentive || (float) $incentive->incentive <= 0) {
                $this->markBookingChecked($booking);

                return 0;
            }

            $kind = $isAgentBooking ? 'booking' : 'referral';
            $count = $this->accrueTransport($booking, $agentId, $kind, $incentive)
                + $this->accrueHotelReservations($booking, $agentId, $kind, $incentive)
                + $this->accrueHotelItems($booking, $agentId, $kind, $incentive);
            $this->markBookingChecked($booking);

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
            ['source_key' => "{$serviceType}:{$sourceType}:{$sourceId}"],
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
