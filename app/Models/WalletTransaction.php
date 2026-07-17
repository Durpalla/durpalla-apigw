<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WalletTransaction extends Model
{
    protected $table = 'wallet_transactions';

    public const TYPE_CREDIT = 'credit';
    public const TYPE_DEBIT = 'debit';

    public const SOURCE_RECHARGE_ONLINE = 'recharge_online';
    public const SOURCE_RECHARGE_MANUAL = 'recharge_manual';
    public const SOURCE_BOOKING = 'booking';
    public const SOURCE_REFUND = 'refund';
    public const SOURCE_REVERSAL = 'reversal';
    public const SOURCE_ADJUSTMENT = 'adjustment';

    protected $fillable = [
        'party_id',
        'type',
        'source',
        'amount',
        'balance_before',
        'balance_after',
        'reference',
        'meta',
        'performed_by',
        'performed_by_type',
        'description',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'balance_before' => 'decimal:2',
        'balance_after' => 'decimal:2',
        'meta' => 'array',
    ];

    public function party(): BelongsTo
    {
        return $this->belongsTo(Party::class, 'party_id');
    }
}
