<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\FinancialEvent;
use App\Models\PartyBalance;
use App\Models\Payment;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * API-gateway mirror of the finance spine (shared DB with durpalla).
 * Agent commission settle/void and booking-paid events are recorded here when
 * apigw runs commission:journey-complete or booking complete flows.
 */
class FinancialLedgerService
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public function record(array $payload): ?FinancialEvent
    {
        if (! Schema::hasTable('financial_events')) {
            return null;
        }

        $amount = round(abs((float) ($payload['amount'] ?? 0)), 2);
        if ($amount <= 0) {
            return null;
        }

        $attributes = [
            'event_type' => (string) $payload['event_type'],
            'debit_party_type' => (string) $payload['debit_party_type'],
            'debit_party_id' => $payload['debit_party_id'] ?? null,
            'credit_party_type' => (string) $payload['credit_party_type'],
            'credit_party_id' => $payload['credit_party_id'] ?? null,
            'amount' => $amount,
            'currency' => $payload['currency'] ?? 'BDT',
            'booking_id' => $payload['booking_id'] ?? null,
            'booking_item_id' => $payload['booking_item_id'] ?? null,
            'trip_id' => $payload['trip_id'] ?? null,
            'source_table' => $payload['source_table'] ?? null,
            'source_id' => $payload['source_id'] ?? null,
            'meta' => $payload['meta'] ?? null,
            'occurred_at' => $payload['occurred_at'] ?? now(),
        ];

        $key = $payload['idempotency_key'] ?? null;

        return DB::transaction(function () use ($attributes, $key, $payload, $amount) {
            if ($key) {
                $existing = FinancialEvent::query()->where('idempotency_key', $key)->first();
                if ($existing) {
                    return $existing;
                }
                $attributes['idempotency_key'] = $key;
            }

            $event = FinancialEvent::query()->create($attributes);

            if (! empty($payload['debit_balance_code'])) {
                $this->adjustBalance(
                    $attributes['debit_party_type'],
                    $attributes['debit_party_id'],
                    (string) $payload['debit_balance_code'],
                    -$amount,
                    $attributes['currency']
                );
            }
            if (! empty($payload['credit_balance_code'])) {
                $this->adjustBalance(
                    $attributes['credit_party_type'],
                    $attributes['credit_party_id'],
                    (string) $payload['credit_balance_code'],
                    $amount,
                    $attributes['currency']
                );
            }

            return $event;
        });
    }

    public function adjustBalance(
        string $partyType,
        ?int $partyId,
        string $balanceCode,
        float $delta,
        string $currency = 'BDT'
    ): ?PartyBalance {
        if (! Schema::hasTable('party_balances')) {
            return null;
        }

        $row = PartyBalance::query()
            ->where('party_type', $partyType)
            ->where('party_id', $partyId)
            ->where('balance_code', $balanceCode)
            ->where('currency', $currency)
            ->lockForUpdate()
            ->first();

        if (! $row) {
            $row = PartyBalance::query()->create([
                'party_type' => $partyType,
                'party_id' => $partyId,
                'balance_code' => $balanceCode,
                'balance' => 0,
                'currency' => $currency,
            ]);
            $row = PartyBalance::query()->whereKey($row->id)->lockForUpdate()->first();
        }

        $row->balance = round((float) $row->balance + $delta, 2);
        $row->save();

        return $row;
    }

    public function recordBookingPaid(Booking $booking): void
    {
        $booking->loadMissing(['payments', 'bookingItems']);
        $payment = $booking->payments->sortByDesc('id')->first()
            ?? Payment::query()->where('booking_id', $booking->id)->orderByDesc('id')->first();

        $paidAmount = $payment
            ? (float) ($payment->paid_amount ?? $payment->store_amount ?? 0)
            : (float) ($booking->total_amount ?? 0);

        $party = strtolower((string) ($booking->booking_party ?? ''));
        $channel = strtolower((string) ($payment->channel ?? ''));
        $method = strtolower((string) ($payment->payment_method ?? ''));
        $collectorType = FinancialEvent::PARTY_PLATFORM;
        $collectorKey = 'durpalla';
        if ($method === 'fund') {
            $collectorKey = 'agent_fund';
        } elseif ($channel === 'merchant' || $party === 'merchant') {
            $collectorType = FinancialEvent::PARTY_MERCHANT;
            $collectorKey = 'merchant';
        }

        $this->record([
            'event_type' => FinancialEvent::TYPE_PAYMENT_CAPTURED,
            'amount' => $paidAmount,
            'debit_party_type' => FinancialEvent::PARTY_CUSTOMER,
            'debit_party_id' => $booking->customer_id ? (int) $booking->customer_id : null,
            'credit_party_type' => $collectorType,
            'credit_party_id' => null,
            'booking_id' => (int) $booking->id,
            'source_table' => $payment ? 'payments' : 'bookings',
            'source_id' => $payment ? (int) $payment->id : (int) $booking->id,
            'idempotency_key' => 'payment_captured:booking:'.$booking->id,
            'meta' => [
                'collector' => $collectorKey,
                'booking_party' => $booking->booking_party,
                'platform' => $booking->platform,
            ],
            'credit_balance_code' => PartyBalance::CODE_CASH_ON_HAND,
        ]);

        $charge = (float) ($booking->charge_total ?? 0);
        if ($charge > 0) {
            $this->record([
                'event_type' => FinancialEvent::TYPE_PLATFORM_CHARGE,
                'amount' => $charge,
                'debit_party_type' => FinancialEvent::PARTY_CUSTOMER,
                'debit_party_id' => $booking->customer_id ? (int) $booking->customer_id : null,
                'credit_party_type' => FinancialEvent::PARTY_PLATFORM,
                'credit_party_id' => null,
                'booking_id' => (int) $booking->id,
                'source_table' => 'bookings',
                'source_id' => (int) $booking->id,
                'idempotency_key' => 'platform_charge:booking:'.$booking->id,
                'credit_balance_code' => PartyBalance::CODE_RECEIVABLE,
            ]);
        }

        $vat = (float) ($booking->vat_total ?? 0);
        if ($vat > 0) {
            $this->record([
                'event_type' => FinancialEvent::TYPE_VAT_ACCRUED,
                'amount' => $vat,
                'debit_party_type' => FinancialEvent::PARTY_PLATFORM,
                'debit_party_id' => null,
                'credit_party_type' => FinancialEvent::PARTY_VAT_AUTHORITY,
                'credit_party_id' => null,
                'booking_id' => (int) $booking->id,
                'source_table' => 'bookings',
                'source_id' => (int) $booking->id,
                'idempotency_key' => 'vat_accrued:bookings:'.$booking->id,
                'debit_balance_code' => PartyBalance::CODE_VAT_PAYABLE,
            ]);
        }
    }

    public function recordAgentCommissionSettled(int $agentId, float $amount, int $accrualId, ?int $bookingId = null, ?int $bookingItemId = null): ?FinancialEvent
    {
        return $this->record([
            'event_type' => FinancialEvent::TYPE_AGENT_COMMISSION_SETTLED,
            'amount' => $amount,
            'debit_party_type' => FinancialEvent::PARTY_AGENT,
            'debit_party_id' => $agentId,
            'credit_party_type' => FinancialEvent::PARTY_AGENT,
            'credit_party_id' => $agentId,
            'booking_id' => $bookingId,
            'booking_item_id' => $bookingItemId,
            'source_table' => 'agent_commission_accruals',
            'source_id' => $accrualId,
            'idempotency_key' => 'agent_commission_settled:'.$accrualId,
            'debit_balance_code' => PartyBalance::CODE_COMMISSION_PENDING,
            'credit_balance_code' => PartyBalance::CODE_COMMISSION_AVAILABLE,
        ]);
    }

    public function recordAgentCommissionAccrued(int $agentId, float $amount, int $accrualId, ?int $bookingId = null, ?int $bookingItemId = null): ?FinancialEvent
    {
        return $this->record([
            'event_type' => FinancialEvent::TYPE_AGENT_COMMISSION_ACCRUED,
            'amount' => $amount,
            'debit_party_type' => FinancialEvent::PARTY_PLATFORM,
            'debit_party_id' => null,
            'credit_party_type' => FinancialEvent::PARTY_AGENT,
            'credit_party_id' => $agentId,
            'booking_id' => $bookingId,
            'booking_item_id' => $bookingItemId,
            'source_table' => 'agent_commission_accruals',
            'source_id' => $accrualId,
            'idempotency_key' => 'agent_commission_accrued:'.$accrualId,
            'credit_balance_code' => PartyBalance::CODE_COMMISSION_PENDING,
        ]);
    }

    public function recordAgentCommissionVoided(int $agentId, float $amount, int $accrualId): ?FinancialEvent
    {
        return $this->record([
            'event_type' => FinancialEvent::TYPE_AGENT_COMMISSION_VOIDED,
            'amount' => $amount,
            'debit_party_type' => FinancialEvent::PARTY_AGENT,
            'debit_party_id' => $agentId,
            'credit_party_type' => FinancialEvent::PARTY_PLATFORM,
            'credit_party_id' => null,
            'source_table' => 'agent_commission_accruals',
            'source_id' => $accrualId,
            'idempotency_key' => 'agent_commission_voided:'.$accrualId,
            'debit_balance_code' => PartyBalance::CODE_COMMISSION_PENDING,
        ]);
    }

    public function recordAgentCommissionReversed(int $agentId, float $amount, int $accrualId): ?FinancialEvent
    {
        return $this->record([
            'event_type' => FinancialEvent::TYPE_AGENT_COMMISSION_REVERSED,
            'amount' => $amount,
            'debit_party_type' => FinancialEvent::PARTY_AGENT,
            'debit_party_id' => $agentId,
            'credit_party_type' => FinancialEvent::PARTY_PLATFORM,
            'credit_party_id' => null,
            'source_table' => 'agent_commission_accruals',
            'source_id' => $accrualId,
            'idempotency_key' => 'agent_commission_reversed:'.$accrualId,
            'debit_balance_code' => PartyBalance::CODE_COMMISSION_AVAILABLE,
        ]);
    }
}
