<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FinancialEvent extends Model
{
    public const TYPE_PAYMENT_CAPTURED = 'payment_captured';
    public const TYPE_PLATFORM_CHARGE = 'platform_charge';
    public const TYPE_SAAS_COMMISSION = 'saas_commission';
    public const TYPE_AGENT_COMMISSION_ACCRUED = 'agent_commission_accrued';
    public const TYPE_AGENT_COMMISSION_SETTLED = 'agent_commission_settled';
    public const TYPE_AGENT_COMMISSION_VOIDED = 'agent_commission_voided';
    public const TYPE_AGENT_COMMISSION_REVERSED = 'agent_commission_reversed';
    public const TYPE_VAT_ACCRUED = 'vat_accrued';
    public const TYPE_VAT_REVERSED = 'vat_reversed';
    public const TYPE_VAT_REMITTED = 'vat_remitted';
    public const TYPE_MERCHANT_SETTLEMENT_ACCRUED = 'merchant_settlement_accrued';
    public const TYPE_MERCHANT_SETTLEMENT_PAID = 'merchant_settlement_paid';
    public const TYPE_SUPERVISOR_CASH_DECLARED = 'supervisor_cash_declared';
    public const TYPE_SUPERVISOR_CASH_APPROVED = 'supervisor_cash_approved';
    public const TYPE_REFUND = 'refund';
    public const TYPE_REVERSAL = 'reversal';

    public const PARTY_PLATFORM = 'platform';
    public const PARTY_MERCHANT = 'merchant';
    public const PARTY_AGENT = 'agent';
    public const PARTY_SUPERVISOR = 'supervisor';
    public const PARTY_VAT_AUTHORITY = 'vat_authority';
    public const PARTY_GATEWAY = 'gateway';
    public const PARTY_CUSTOMER = 'customer';

    protected $fillable = [
        'event_type',
        'debit_party_type',
        'debit_party_id',
        'credit_party_type',
        'credit_party_id',
        'amount',
        'currency',
        'booking_id',
        'booking_item_id',
        'trip_id',
        'source_table',
        'source_id',
        'idempotency_key',
        'meta',
        'occurred_at',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'meta' => 'array',
        'occurred_at' => 'datetime',
    ];
}
