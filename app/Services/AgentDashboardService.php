<?php

namespace App\Services;

use App\Models\Agent;
use App\Models\AgentCommission;
use App\Models\AgentReferredMerchant;
use App\Models\Booking;
use App\Models\Customer;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AgentDashboardService
{
    private const CUSTOMER_PLATFORMS = ['android', 'ios', 'web'];

    public function dashboard(int $agentId, int $chartDays = 7): array
    {
        $today = Carbon::today();
        $yesterday = Carbon::yesterday();
        $chartDays = max(7, min(30, $chartDays));

        $earningsToday = $this->earningsOn($agentId, $today->toDateString());
        $earningsYesterday = $this->earningsOn($agentId, $yesterday->toDateString());

        $merchantStats = AgentReferredMerchant::query()
            ->where('agent_id', $agentId)
            ->selectRaw('COUNT(*) as total')
            ->selectRaw("SUM(CASE WHEN status = 'live' THEN 1 ELSE 0 END) as active")
            ->selectRaw("SUM(CASE WHEN status IN ('lead','submitted','under_review','rejected') THEN 1 ELSE 0 END) as pending")
            ->first();

        $bookingsToday = $this->bookingsQuery($agentId, 'all')
            ->whereDate('bookings.booking_date', $today)
            ->count();

        return [
            'earnings' => [
                'today' => $earningsToday,
                'yesterday' => $earningsYesterday,
                'delta' => round($earningsToday - $earningsYesterday, 2),
                'total_earned' => (float) AgentCommission::query()->where('user_id', $agentId)->sum('amount'),
            ],
            'bookings' => [
                'today_count' => $bookingsToday,
            ],
            'properties' => [
                'total' => (int) ($merchantStats->total ?? 0),
                'active' => (int) ($merchantStats->active ?? 0),
                'pending' => (int) ($merchantStats->pending ?? 0),
            ],
            'merchants' => [
                'total' => (int) ($merchantStats->total ?? 0),
                'live' => (int) ($merchantStats->active ?? 0),
                'pending' => (int) ($merchantStats->pending ?? 0),
            ],
            'chart' => [
                'granularity' => 'day',
                'series' => $this->chartSeries($agentId, $chartDays),
            ],
        ];
    }

    public function bookings(
        int $agentId,
        int $page = 1,
        int $size = 20,
        string $source = 'all',
        ?string $dateFrom = null,
        ?string $dateTo = null,
    ): LengthAwarePaginator {
        $query = $this->bookingsQuery($agentId, $source)
            ->with(['customer', 'bookingItems']);

        if ($dateFrom) {
            $query->whereDate('bookings.booking_date', '>=', $dateFrom);
        }
        if ($dateTo) {
            $query->whereDate('bookings.booking_date', '<=', $dateTo);
        }

        return $query
            ->orderByDesc('bookings.booking_date')
            ->orderByDesc('bookings.id')
            ->paginate($size, ['bookings.*'], 'page', $page);
    }

    public function bookingsQuery(int $agentId, string $source = 'all'): Builder
    {
        // Write PDO: schema checks + queries must agree (MySQL Router read port 6447 can lag DDL).
        if ($source === 'counter') {
            return $this->counterBookingsQuery($agentId);
        }

        if ($source === 'referral') {
            return $this->customerReferralBookingsQuery($agentId);
        }

        $query = $this->newBookingQuery();

        return $query->where(function (Builder $q) use ($agentId) {
            if ($this->hasBookedByColumns()) {
                $q->where(function (Builder $counter) use ($agentId) {
                    $this->applyCounterConstraints($counter, $agentId);
                })->orWhere(function (Builder $referral) use ($agentId) {
                    $this->applyReferralConstraints($referral, $agentId);
                });

                return;
            }

            $this->applyReferralConstraints($q, $agentId);
        });
    }

    public function customerReferralBookingsQuery(int $agentId): Builder
    {
        $query = $this->newBookingQuery();
        $this->applyReferralConstraints($query, $agentId);

        if (Schema::hasColumn('bookings', 'platform')) {
            $query->whereIn('bookings.platform', self::CUSTOMER_PLATFORMS);
        }

        if (! $this->hasBookedByColumns()) {
            return $query;
        }

        return $query
            ->where(function (Builder $q) {
                $q->whereNull('bookings.booked_by_type')
                    ->orWhere('bookings.booked_by_type', Customer::class)
                    ->orWhereColumn('bookings.booked_by_id', 'bookings.customer_id');
            })
            ->where(function (Builder $q) {
                $q->whereNull('bookings.booked_by_type')
                    ->orWhereNotIn('bookings.booked_by_type', [
                        Agent::class,
                        User::class,
                    ]);
            });
    }

    private function counterBookingsQuery(int $agentId): Builder
    {
        $query = $this->newBookingQuery();

        if (! $this->hasBookedByColumns()) {
            return $query->whereRaw('0 = 1');
        }

        $this->applyCounterConstraints($query, $agentId);

        return $query;
    }

    private function newBookingQuery(): Builder
    {
        return Booking::query()->useWritePdo();
    }

    private function applyCounterConstraints(Builder $query, int $agentId): void
    {
        $query->where('bookings.booked_by_type', Agent::class)
            ->where('bookings.booked_by_id', $agentId);
    }

    private function applyReferralConstraints(Builder $query, int $agentId): void
    {
        $merchantIds = AgentReferredMerchant::query()
            ->where('agent_id', $agentId)
            ->where('status', AgentReferredMerchant::STATUS_LIVE)
            ->whereNotNull('merchant_id')
            ->pluck('merchant_id')
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();

        $query->where(function (Builder $outer) use ($agentId, $merchantIds) {
            if ($this->hasReferringAgentColumn()) {
                $outer->where('bookings.referring_agent_id', $agentId);
            } else {
                $outer->whereRaw('0 = 1');
            }

            if ($merchantIds === []) {
                return;
            }

            $outer->orWhereHas('bookingItems', function (Builder $items) use ($merchantIds) {
                $items->where(function (Builder $q) use ($merchantIds) {
                    $q->whereHas('vehicle', function (Builder $v) use ($merchantIds) {
                        $v->whereIn('merchant_id', $merchantIds);
                    });

                    if (
                        Schema::hasColumn('booking_items', 'hotel_id')
                        && Schema::hasTable('hotels')
                        && Schema::hasColumn('hotels', 'merchant_id')
                    ) {
                        $q->orWhereIn('hotel_id', function ($sub) use ($merchantIds) {
                            $sub->select('id')->from('hotels')->whereIn('merchant_id', $merchantIds);
                        });
                    }
                });
            });
        });
    }

    private function hasBookedByColumns(): bool
    {
        static $cached = null;
        if ($cached !== null) {
            return $cached;
        }

        $cached = $this->columnsExistOnWrite('bookings', ['booked_by_type', 'booked_by_id']);

        return $cached;
    }

    private function hasReferringAgentColumn(): bool
    {
        static $cached = null;
        if ($cached !== null) {
            return $cached;
        }

        $cached = $this->columnsExistOnWrite('bookings', ['referring_agent_id']);

        return $cached;
    }

    /**
     * Inspect schema via the write PDO so MySQL Router read replicas (6447) cannot lie.
     */
    private function columnsExistOnWrite(string $table, array $columns): bool
    {
        $placeholders = implode(',', array_fill(0, count($columns), '?'));
        $bindings = array_merge([$table], $columns);

        $rows = DB::connection()->select(
            "select column_name from information_schema.columns
             where table_schema = database()
               and table_name = ?
               and column_name in ({$placeholders})",
            $bindings,
            false
        );

        $found = collect($rows)
            ->pluck('column_name')
            ->map(fn ($name) => strtolower((string) $name))
            ->unique()
            ->values()
            ->all();

        foreach ($columns as $column) {
            if (! in_array(strtolower($column), $found, true)) {
                return false;
            }
        }

        return true;
    }

    private function earningsOn(int $agentId, string $date): float
    {
        return (float) AgentCommission::query()
            ->where('user_id', $agentId)
            ->whereDate('commission_date', $date)
            ->sum('amount');
    }

    private function chartSeries(int $agentId, int $days): array
    {
        $series = [];
        for ($i = $days - 1; $i >= 0; $i--) {
            $day = Carbon::today()->subDays($i);
            $series[] = [
                'date' => $day->toDateString(),
                'label' => $day->format('D'),
                'amount' => $this->earningsOn($agentId, $day->toDateString()),
            ];
        }

        return $series;
    }
}
