<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MerchantCancellationTier extends Model
{
    protected $table = 'merchant_cancellation_tiers';

    protected $fillable = [
        'merchant_id',
        'service_type',
        'min_hours_before',
        'refund_percent',
        'sort',
    ];

    protected $casts = [
        'min_hours_before' => 'integer',
        'refund_percent' => 'decimal:2',
        'sort' => 'integer',
    ];
}
