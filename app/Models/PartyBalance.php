<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PartyBalance extends Model
{
    public const CODE_CASH_ON_HAND = 'cash_on_hand';
    public const CODE_PAYABLE = 'payable';
    public const CODE_RECEIVABLE = 'receivable';
    public const CODE_COMMISSION_PENDING = 'commission_pending';
    public const CODE_COMMISSION_AVAILABLE = 'commission_available';
    public const CODE_VAT_PAYABLE = 'vat_payable';
    public const CODE_SETTLEMENT_PENDING = 'settlement_pending';

    protected $fillable = [
        'party_type',
        'party_id',
        'balance_code',
        'balance',
        'currency',
    ];

    protected $casts = [
        'balance' => 'decimal:2',
    ];
}
