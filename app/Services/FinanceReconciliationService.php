<?php

namespace App\Services;

use App\Constants\AppConst;
use App\Models\Booking;
use App\Models\FinancialEvent;
use App\Models\MerchantSettlement;
use App\Models\Payment;
use App\Models\PaymentCollector;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use App\Models\SaasCommissionLedger;

/**
 * Phase 0 reconciliation: Durpalla-collected bookings vs settlements vs SaaS ledger.
 */
class FinanceReconciliationService
{
    /**
     * @return array<string, mixed>
     */
    public function summarize(?string $dateFrom = null, ?string $dateTo = null, ?int $merchantId = null): array
    {
        $from = $dateFrom ?: now()->subDays(30)->toDateString();
        $to = $dateTo ?: now()->toDateString();

        $durpallaCollected = $this->sumDurpallaCollectedPayments($from, $to, $merchantId);
        $saasAccrued = $this->sumSaasCommission($from, $to, $merchantId, SaasCommissionLedger::STATUS_ACCRUED);
        $saasSettled = $this->sumSaasCommission($from, $to, $merchantId, SaasCommissionLedger::STATUS_SETTLED);
        $settlementsPending = $this->sumSettlements($from, $to, $merchantId, MerchantSettlement::STATUS_PENDING);
        $settlementsPaid = $this->sumSettlements($from, $to, $merchantId, MerchantSettlement::STATUS_PAID);
        $platformChargeEvents = $this->sumFinancialEvents(FinancialEvent::TYPE_PLATFORM_CHARGE, $from, $to, $merchantId);
        $saasEvents = $this->sumFinancialEvents(FinancialEvent::TYPE_SAAS_COMMISSION, $from, $to, $merchantId);
        $vatOutstanding = $this->vatOutstanding($merchantId);
        $supervisorCashPending = $this->supervisorCashPending($from, $to, $merchantId);

        return [
            'date_from' => $from,
            'date_to' => $to,
            'merchant_id' => $merchantId,
            'durpalla_collected_payments' => $durpallaCollected,
            'saas_commission_accrued' => $saasAccrued,
            'saas_commission_settled' => $saasSettled,
            'merchant_settlements_pending' => $settlementsPending,
            'merchant_settlements_paid' => $settlementsPaid,
            'financial_events_platform_charge' => $platformChargeEvents,
            'financial_events_saas_commission' => $saasEvents,
            'vat_payable_outstanding' => $vatOutstanding,
            'supervisor_cash_pending' => $supervisorCashPending,
            'gaps' => [
                'saas_unsettled' => round($saasAccrued['commission'] - 0, 2),
                'settlement_net_pending' => $settlementsPending['merchant_amount'],
            ],
        ];
    }

    /**
     * @return array{count:int,paid_amount:float,store_amount:float}
     */
    private function sumDurpallaCollectedPayments(string $from, string $to, ?int $merchantId): array
    {
        $q = Payment::query()
            ->join('bookings', 'bookings.id', '=', 'payments.booking_id')
            ->whereIn('payments.status', ['success', 'verified', 1, '1'])
            ->where(function ($q) {
                $q->where('payments.channel', 'live')
                    ->orWhere('bookings.booking_party', AppConst::PARTY_DURPALLA ?? 'durpalla');
            })
            ->whereBetween(DB::raw('DATE(COALESCE(payments.updated_at, payments.created_at))'), [$from, $to]);

        if ($merchantId) {
            $q->whereExists(function ($sub) use ($merchantId) {
                $sub->select(DB::raw(1))
                    ->from('booking_items')
                    ->join('vehicles', 'vehicles.id', '=', 'booking_items.vehicle_id')
                    ->whereColumn('booking_items.booking_id', 'bookings.id')
                    ->where('vehicles.merchant_id', $merchantId);
            });
        }

        return [
            'count' => (int) (clone $q)->count(),
            'paid_amount' => round((float) (clone $q)->sum('payments.paid_amount'), 2),
            'store_amount' => round((float) (clone $q)->sum('payments.store_amount'), 2),
        ];
    }

    /**
     * @return array{count:int,base:float,commission:float}
     */
    private function sumSaasCommission(string $from, string $to, ?int $merchantId, string $status): array
    {
        if (! Schema::hasTable('saas_commission_ledgers')) {
            return ['count' => 0, 'base' => 0.0, 'commission' => 0.0];
        }

        $q = SaasCommissionLedger::query()
            ->join('bookings', 'bookings.id', '=', 'saas_commission_ledgers.booking_id')
            ->where('saas_commission_ledgers.status', $status)
            ->whereBetween('bookings.booking_date', [$from, $to]);

        if ($merchantId) {
            $q->where('saas_commission_ledgers.merchant_id', $merchantId);
        }

        return [
            'count' => (int) (clone $q)->count(),
            'base' => round((float) (clone $q)->sum('saas_commission_ledgers.base_amount'), 2),
            'commission' => round((float) (clone $q)->sum('saas_commission_ledgers.commission_amount'), 2),
        ];
    }

    /**
     * @return array{count:int,merchant_amount:float,platform_charge:float,gross_payable:float,commission_receivable:float}
     */
    private function sumSettlements(string $from, string $to, ?int $merchantId, string $status): array
    {
        if (! Schema::hasTable('merchant_settlements')) {
            return [
                'count' => 0,
                'merchant_amount' => 0.0,
                'platform_charge' => 0.0,
                'gross_payable' => 0.0,
                'commission_receivable' => 0.0,
            ];
        }

        $q = MerchantSettlement::query()
            ->where('status', $status)
            ->where(function ($q) use ($from, $to) {
                $q->whereBetween('period_from', [$from, $to])
                    ->orWhereBetween('period_to', [$from, $to]);
            });

        if ($merchantId) {
            $q->where('merchant_id', $merchantId);
        }

        return [
            'count' => (int) (clone $q)->count(),
            'merchant_amount' => round((float) (clone $q)->sum('merchant_amount'), 2),
            'platform_charge' => round((float) (clone $q)->sum('platform_charge'), 2),
            'gross_payable' => round((float) (clone $q)->sum('gross_merchant_payable'), 2),
            'commission_receivable' => round((float) (clone $q)->sum('commission_receivable'), 2),
        ];
    }

    /**
     * @return array{count:int,amount:float}
     */
    private function sumFinancialEvents(string $type, string $from, string $to, ?int $merchantId): array
    {
        if (! Schema::hasTable('financial_events')) {
            return ['count' => 0, 'amount' => 0.0];
        }

        $q = FinancialEvent::query()
            ->where('event_type', $type)
            ->whereBetween(DB::raw('DATE(COALESCE(occurred_at, created_at))'), [$from, $to]);

        if ($merchantId) {
            $q->where(function ($q) use ($merchantId) {
                $q->where(function ($q) use ($merchantId) {
                    $q->where('debit_party_type', 'merchant')->where('debit_party_id', $merchantId);
                })->orWhere(function ($q) use ($merchantId) {
                    $q->where('credit_party_type', 'merchant')->where('credit_party_id', $merchantId);
                });
            });
        }

        return [
            'count' => (int) (clone $q)->count(),
            'amount' => round((float) (clone $q)->sum('amount'), 2),
        ];
    }

    private function vatOutstanding(?int $merchantId): float
    {
        if (! Schema::hasTable('party_balances')) {
            return 0.0;
        }

        $q = DB::table('party_balances')
            ->where('balance_code', 'vat_payable');

        if ($merchantId) {
            $q->where('party_type', 'merchant')->where('party_id', $merchantId);
        }

        return round((float) $q->sum('balance'), 2);
    }

    /**
     * @return array{count:int,cash_submitted:float}
     */
    private function supervisorCashPending(string $from, string $to, ?int $merchantId): array
    {
        if (! Schema::hasTable('supervisor_settlement_requests')) {
            return ['count' => 0, 'cash_submitted' => 0.0];
        }

        $q = DB::table('supervisor_settlement_requests')
            ->where('status', 'pending')
            ->whereBetween('date', [$from, $to]);

        if ($merchantId) {
            $q->where('merchant_id', $merchantId);
        }

        return [
            'count' => (int) (clone $q)->count(),
            'cash_submitted' => round((float) (clone $q)->sum('cash_submitted'), 2),
        ];
    }

    public function expectedSupervisorCash(int $merchantId, int $supervisorId, string $date, ?int $tripId = null): float
    {
        if (! Schema::hasTable('payment_collectors')) {
            return 0.0;
        }

        $q = PaymentCollector::query()
            ->where('supervisor_id', $supervisorId)
            ->whereDate('created_at', $date);

        if ($tripId && Schema::hasColumn('payment_collectors', 'booking_id')) {
            $q->whereIn('booking_id', function ($sub) use ($tripId) {
                $sub->select('booking_id')
                    ->from('booking_items')
                    ->where('trip_id', $tripId);
            });
        }

        // Scope to merchant via booking items → vehicles when possible.
        if ($merchantId > 0) {
            $q->whereIn('booking_id', function ($sub) use ($merchantId) {
                $sub->select('booking_items.booking_id')
                    ->from('booking_items')
                    ->join('vehicles', 'vehicles.id', '=', 'booking_items.vehicle_id')
                    ->where('vehicles.merchant_id', $merchantId);
            });
        }

        return round((float) $q->sum('amount'), 2);
    }
}
