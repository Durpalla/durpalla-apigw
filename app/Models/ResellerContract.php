<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ResellerContract extends Model
{
    protected $table = 'reseller_contracts';

    protected $fillable = [
        'party_id',
        'commission_share_percent',
        'status',
        'effective_from',
        'notes',
    ];

    protected $casts = [
        'commission_share_percent' => 'decimal:2',
        'status' => 'integer',
        'effective_from' => 'date',
    ];

    public function party(): BelongsTo
    {
        return $this->belongsTo(Party::class, 'party_id');
    }
}
