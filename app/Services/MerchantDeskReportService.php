<?php

namespace App\Services;

use App\Constants\AppConst;
use App\Models\Booking;
use App\Models\BookingItem;
use App\Models\MerchantSettlement;
use App\Models\VehicleSchedule;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Aggregates for Merchant Desk Pro reports (scoped by merchant owner's user_id).
 */
class MerchantDeskReportService
{
    /**
     * @return array<string, mixed>
     */
    public function buildReport(int $merchantUserId, string $from, string $to): array
    {
        $start = Carbon::parse($from)->startOfDay();
        $end = Carbon::parse($to)->endOfDay();
        $days = max(1, $start->diffInDays($end) + 1);
        $prevEnd = (clone $start)->subDay();
        $prevStart = (clone $prevEnd)->subDays($days - 1);

        $cur = $this->metricsForRange($merchantUserId, $start->toDateString(), $end->toDateString());
        $prev = $this->metricsForRange($merchantUserId, $prevStart->toDateString(), $prevEnd->toDateString());
        $series = $this->dailySeries($merchantUserId, $start->toDateString(), $end->toDateString());

        return [
            'from' => $start->toDateString(),
            'to' => $end->toDateString(),
            'previous_period' => [
                'from' => $prevStart->toDateString(),
                'to' => $prevEnd->toDateString(),
            ],
            'metrics' => [
                'total_revenue' => $cur['total_revenue'],
                'total_revenue_trend_percent' => $this->trendPercent($cur['total_revenue'], $prev['total_revenue']),
                'total_bookings' => $cur['total_bookings'],
                'total_bookings_trend_percent' => $this->trendPercent($cur['total_bookings'], $prev['total_bookings']),
                'avg_occupancy_percent' => $cur['avg_occupancy_percent'],
                'avg_occupancy_trend_percent' => $this->occupancyTrendPercent(
                    $cur['avg_occupancy_percent'],
                    $prev['avg_occupancy_percent']
                ),
                'units_sold' => $cur['units_sold'],
                'settlement_total' => $cur['settlement_total'],
            ],
            'series' => $series,
        ];
    }

    /**
     * Snapshot metrics for Merchant Desk dashboard (rolling windows vs prior day / prior 30 days).
     *
     * @return array<string, mixed>
     */
    public function buildDashboardStats(int $merchantUserId): array
    {
        $today = Carbon::now()->toDateString();
        $yesterday = Carbon::now()->subDay()->toDateString();
        $start30 = Carbon::now()->subDays(29)->toDateString();
        $prevEnd30 = Carbon::now()->subDays(30)->toDateString();
        $prevStart30 = Carbon::now()->subDays(59)->toDateString();

        $bookings30 = $this->metricsForRange($merchantUserId, $start30, $today);
        $bookings30Prev = $this->metricsForRange($merchantUserId, $prevStart30, $prevEnd30);

        $activeTripsToday = (int) VehicleSchedule::query()
            ->where('merchant_id', $merchantUserId)
            ->whereDate('schedule_date', $today)
            ->where('status', AppConst::SCHEDULE_ACTIVE)
            ->count();

        $activeTripsYesterday = (int) VehicleSchedule::query()
            ->where('merchant_id', $merchantUserId)
            ->whereDate('schedule_date', $yesterday)
            ->where('status', AppConst::SCHEDULE_ACTIVE)
            ->count();

        $todayM = $this->metricsForRange($merchantUserId, $today, $today);
        $yesterdayM = $this->metricsForRange($merchantUserId, $yesterday, $yesterday);

        $occToday = $this->averageTripOccupancyPercent($merchantUserId, $today, $today);
        $occYesterday = $this->averageTripOccupancyPercent($merchantUserId, $yesterday, $yesterday);

        return [
            'as_of' => $today,
            'total_bookings_30d' => $bookings30['total_bookings'],
            'total_bookings_30d_trend_percent' => $this->trendPercent(
                $bookings30['total_bookings'],
                $bookings30Prev['total_bookings']
            ),
            'active_trips_today' => $activeTripsToday,
            'active_trips_trend_percent' => $this->trendPercent($activeTripsToday, $activeTripsYesterday),
            'revenue_today' => $todayM['total_revenue'],
            'revenue_today_trend_percent' => $this->trendPercent(
                $todayM['total_revenue'],
                $yesterdayM['total_revenue']
            ),
            'avg_occupancy_percent' => $occToday,
            'avg_occupancy_trend_percent' => $this->occupancyTrendPercent($occToday, $occYesterday),
        ];
    }

    /**
     * @return list<array{date:string,revenue:float,bookings:int,units:int}>
     */
    public function dailySeries(int $merchantUserId, string $from, string $to): array
    {
        $bookingIdsSub = $this->merchantBookingIdsSubquery($merchantUserId, $from, $to);
        $latestPayments = $this->latestPaymentIdsSubquery();

        $rows = DB::table('bookings')
            ->joinSub($bookingIdsSub, 'mb', 'mb.id', '=', 'bookings.id')
            ->leftJoinSub($latestPayments, 'lp', 'lp.booking_id', '=', 'bookings.id')
            ->leftJoin('payments as p', function ($join) {
                $join->on('p.id', '=', 'lp.payment_id')->whereNull('p.deleted_at');
            })
            ->whereBetween('bookings.booking_date', [$from, $to])
            ->groupBy('bookings.booking_date')
            ->orderBy('bookings.booking_date')
            ->get([
                'bookings.booking_date as d',
                DB::raw('COUNT(DISTINCT bookings.id) as bookings_count'),
                DB::raw('COALESCE(SUM(p.paid_amount), 0) as revenue'),
            ]);

        $unitsByDate = BookingItem::query()
            ->select([
                'bookings.booking_date as d',
                DB::raw('COUNT(*) as units'),
            ])
            ->join('bookings', 'bookings.id', '=', 'booking_items.booking_id')
            ->join('vehicles', 'vehicles.id', '=', 'booking_items.vehicle_id')
            ->where('vehicles.merchant_id', $merchantUserId)
            ->where('booking_items.status', AppConst::BOOKING_ITEM_ACTIVE)
            ->whereIn('bookings.status', [AppConst::BOOKING_COMPLETE, AppConst::BOOKING_ADVANCE])
            ->whereBetween('bookings.booking_date', [$from, $to])
            ->where(function ($q) {
                $q->whereNull('booking_items.item_type')
                    ->orWhere('booking_items.item_type', 'transport');
            })
            ->groupBy('bookings.booking_date')
            ->pluck('units', 'd');

        $byDate = [];
        foreach ($rows as $r) {
            $d = $r->d;
            $byDate[$d] = [
                'date' => $d,
                'revenue' => round((float) $r->revenue, 2),
                'bookings' => (int) $r->bookings_count,
                'units' => (int) ($unitsByDate[$d] ?? 0),
            ];
        }

        $out = [];
        $cursor = Carbon::parse($from);
        $end = Carbon::parse($to);
        while ($cursor->lte($end)) {
            $ds = $cursor->toDateString();
            $out[] = $byDate[$ds] ?? [
                'date' => $ds,
                'revenue' => 0.0,
                'bookings' => 0,
                'units' => (int) ($unitsByDate[$ds] ?? 0),
            ];
            $cursor->addDay();
        }

        return $out;
    }

    /**
     * @return \Illuminate\Database\Query\Builder
     */
    protected function merchantBookingIdsSubquery(int $merchantUserId, string $from, string $to)
    {
        return Booking::query()
            ->select('bookings.id')
            ->whereBetween('bookings.booking_date', [$from, $to])
            ->whereIn('bookings.status', [AppConst::BOOKING_COMPLETE, AppConst::BOOKING_ADVANCE])
            ->whereExists(function ($q) use ($merchantUserId) {
                $q->selectRaw('1')
                    ->from('booking_items')
                    ->join('vehicles', 'vehicles.id', '=', 'booking_items.vehicle_id')
                    ->whereColumn('booking_items.booking_id', 'bookings.id')
                    ->where('vehicles.merchant_id', $merchantUserId)
                    ->where('booking_items.status', AppConst::BOOKING_ITEM_ACTIVE)
                    ->where(function ($w) {
                        $w->whereNull('booking_items.item_type')
                            ->orWhere('booking_items.item_type', 'transport');
                    });
            });
    }

    protected function latestPaymentIdsSubquery()
    {
        return DB::table('payments')
            ->select([
                'booking_id',
                DB::raw('MAX(id) as payment_id'),
            ])
            ->whereNull('deleted_at')
            ->groupBy('booking_id');
    }

    /**
     * @return array{total_revenue:float,total_bookings:int,avg_occupancy_percent:?float,units_sold:int,settlement_total:float}
     */
    protected function metricsForRange(int $merchantUserId, string $from, string $to): array
    {
        $bookingIdsSub = $this->merchantBookingIdsSubquery($merchantUserId, $from, $to);
        $latestPayments = $this->latestPaymentIdsSubquery();

        $totalBookings = DB::table('bookings')
            ->joinSub($bookingIdsSub, 'mb', 'mb.id', '=', 'bookings.id')
            ->count();

        $totalRevenue = (float) DB::table('bookings')
            ->joinSub($bookingIdsSub, 'mb', 'mb.id', '=', 'bookings.id')
            ->leftJoinSub($latestPayments, 'lp', 'lp.booking_id', '=', 'bookings.id')
            ->leftJoin('payments as p', function ($join) {
                $join->on('p.id', '=', 'lp.payment_id')->whereNull('p.deleted_at');
            })
            ->sum('p.paid_amount');

        $unitsSold = (int) BookingItem::query()
            ->join('bookings', 'bookings.id', '=', 'booking_items.booking_id')
            ->join('vehicles', 'vehicles.id', '=', 'booking_items.vehicle_id')
            ->where('vehicles.merchant_id', $merchantUserId)
            ->where('booking_items.status', AppConst::BOOKING_ITEM_ACTIVE)
            ->whereIn('bookings.status', [AppConst::BOOKING_COMPLETE, AppConst::BOOKING_ADVANCE])
            ->whereBetween('bookings.booking_date', [$from, $to])
            ->where(function ($q) {
                $q->whereNull('booking_items.item_type')
                    ->orWhere('booking_items.item_type', 'transport');
            })
            ->count();

        $avgOccupancy = $this->averageTripOccupancyPercent($merchantUserId, $from, $to);

        $settlementTotal = 0.0;
        if (Schema::hasTable('merchant_settlements')) {
            $settlementTotal = (float) MerchantSettlement::query()
                ->where('merchant_id', $merchantUserId)
                ->where('status', MerchantSettlement::STATUS_PAID)
                ->where(function ($q) use ($from, $to) {
                    $q->whereBetween('period_from', [$from, $to])
                        ->orWhereBetween('period_to', [$from, $to])
                        ->orWhere(function ($q2) use ($from, $to) {
                            $q2->where('period_from', '<=', $from)->where('period_to', '>=', $to);
                        });
                })
                ->sum('merchant_amount');
        }

        return [
            'total_revenue' => round($totalRevenue, 2),
            'total_bookings' => $totalBookings,
            'avg_occupancy_percent' => $avgOccupancy,
            'units_sold' => $unitsSold,
            'settlement_total' => round($settlementTotal, 2),
        ];
    }

    protected function averageTripOccupancyPercent(int $merchantUserId, string $from, string $to): ?float
    {
        $rows = DB::table('booking_items')
            ->join('bookings', 'bookings.id', '=', 'booking_items.booking_id')
            ->join('vehicles', 'vehicles.id', '=', 'booking_items.vehicle_id')
            ->join('vehicle_schedules as vs', 'vs.id', '=', 'booking_items.trip_id')
            ->join('vehicles as launch', 'launch.id', '=', 'vs.vehicle_id')
            ->where('vehicles.merchant_id', $merchantUserId)
            ->where('booking_items.status', AppConst::BOOKING_ITEM_ACTIVE)
            ->whereIn('bookings.status', [AppConst::BOOKING_COMPLETE, AppConst::BOOKING_ADVANCE])
            ->whereBetween('bookings.booking_date', [$from, $to])
            ->whereNotNull('booking_items.trip_id')
            ->where('launch.passengers_capacity', '>', 0)
            ->where(function ($q) {
                $q->whereNull('booking_items.item_type')
                    ->orWhere('booking_items.item_type', 'transport');
            })
            ->groupBy('booking_items.trip_id', 'launch.passengers_capacity')
            ->selectRaw('booking_items.trip_id, launch.passengers_capacity as cap, COUNT(*) as sold')
            ->get();

        if ($rows->isEmpty()) {
            return null;
        }

        $percents = $rows->map(function ($r) {
            $p = 100.0 * (int) $r->sold / (int) $r->cap;

            return min(100.0, round($p, 1));
        });

        return round($percents->avg(), 1);
    }

    protected function trendPercent(float|int $current, float|int $previous): ?float
    {
        if ($previous == 0) {
            if ($current == 0) {
                return 0.0;
            }

            return 100.0;
        }

        return round((($current - $previous) / $previous) * 100, 1);
    }

    protected function occupancyTrendPercent(?float $current, ?float $previous): ?float
    {
        if ($current === null || $previous === null) {
            return null;
        }

        return $this->trendPercent($current, $previous);
    }

    /**
     * @return \Symfony\Component\HttpFoundation\StreamedResponse
     */
    public function streamCsv(int $merchantUserId, string $from, string $to, string $reportType = 'Sales')
    {
        $series = $this->dailySeries($merchantUserId, $from, $to);
        $slug = Str::slug($reportType) ?: 'report';
        $filename = 'merchant-report-'.$slug.'-'.$from.'-to-'.$to.'.csv';

        return response()->streamDownload(function () use ($series) {
            $out = fopen('php://output', 'w');
            // UTF-8 BOM so Excel opens CSV correctly.
            fprintf($out, chr(0xEF).chr(0xBB).chr(0xBF));
            fputcsv($out, ['date', 'revenue', 'bookings', 'units_sold']);
            foreach ($series as $row) {
                fputcsv($out, [$row['date'], $row['revenue'], $row['bookings'], $row['units']]);
            }
            fclose($out);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }
}
