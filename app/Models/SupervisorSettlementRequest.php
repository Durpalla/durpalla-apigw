<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SupervisorSettlementRequest extends Model
{
    protected $fillable = [
        'merchant_id',
        'supervisor_id',
        'date',
        'cash_submitted',
        'expected_cash',
        'variance',
        'trip_id',
        'notes',
        'status',
        'decided_by',
        'decided_at',
    ];

    protected $casts = [
        'date' => 'date',
        'cash_submitted' => 'decimal:2',
        'expected_cash' => 'decimal:2',
        'variance' => 'decimal:2',
        'decided_at' => 'datetime',
    ];

    public const STATUS_PENDING = 'pending';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_DECLINED = 'declined';

    public function merchantOwner(): BelongsTo
    {
        return $this->belongsTo(Merchant::class, 'merchant_id', 'id');
    }

    public function supervisor(): BelongsTo
    {
        return $this->belongsTo(MerchantStaff::class, 'supervisor_id', 'id');
    }

    public function decider(): BelongsTo
    {
        return $this->belongsTo(Merchant::class, 'decided_by', 'id');
    }
}
