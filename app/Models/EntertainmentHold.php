<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EntertainmentHold extends Model
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_CONSUMED = 'consumed';
    public const STATUS_CANCELLED = 'cancelled';
    public const STATUS_EXPIRED = 'expired';

    protected $fillable = [
        'venue_id', 'user_id', 'merchant_id', 'agent_id', 'visit_date', 'slot_template_id',
        'lines_json', 'total_qty', 'total_price', 'status', 'expires_at', 'guest_json', 'idempotency_key',
    ];

    protected function casts(): array
    {
        return [
            'venue_id' => 'integer',
            'user_id' => 'integer',
            'merchant_id' => 'integer',
            'agent_id' => 'integer',
            'visit_date' => 'date',
            'slot_template_id' => 'integer',
            'lines_json' => 'array',
            'total_qty' => 'integer',
            'total_price' => 'decimal:2',
            'expires_at' => 'datetime',
            'guest_json' => 'array',
        ];
    }

    public function venue(): BelongsTo
    {
        return $this->belongsTo(EntertainmentVenue::class, 'venue_id', 'id');
    }
}
