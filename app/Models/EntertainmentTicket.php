<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EntertainmentTicket extends Model
{
    public const STATUS_VALID = 'valid';
    public const STATUS_USED = 'used';
    public const STATUS_CANCELLED = 'cancelled';
    public const STATUS_EXPIRED = 'expired';

    protected $fillable = [
        'booking_id', 'booking_item_id', 'venue_id', 'ticket_type_id', 'inventory_id',
        'code', 'qr_token', 'visitor_type', 'visitor_label', 'price',
        'purchased_at', 'valid_from', 'valid_until', 'max_redemptions', 'redemption_count',
        'status', 'override_accepted', 'redeemed_at', 'redeemed_by',
    ];

    protected function casts(): array
    {
        return [
            'booking_id' => 'integer',
            'booking_item_id' => 'integer',
            'venue_id' => 'integer',
            'ticket_type_id' => 'integer',
            'inventory_id' => 'integer',
            'price' => 'decimal:2',
            'purchased_at' => 'datetime',
            'valid_from' => 'datetime',
            'valid_until' => 'datetime',
            'max_redemptions' => 'integer',
            'redemption_count' => 'integer',
            'override_accepted' => 'boolean',
            'redeemed_at' => 'datetime',
            'redeemed_by' => 'integer',
        ];
    }

    public function venue(): BelongsTo
    {
        return $this->belongsTo(EntertainmentVenue::class, 'venue_id', 'id');
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class, 'booking_id', 'id');
    }

    public function events(): HasMany
    {
        return $this->hasMany(EntertainmentTicketEvent::class, 'ticket_id', 'id');
    }

    public function isExpiredNow(): bool
    {
        return $this->valid_until !== null && now()->greaterThan($this->valid_until);
    }
}
