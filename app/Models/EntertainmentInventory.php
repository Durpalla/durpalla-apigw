<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EntertainmentInventory extends Model
{
    protected $table = 'entertainment_inventory';

    protected $fillable = [
        'venue_id', 'ticket_type_id', 'slot_template_id', 'visit_date',
        'capacity', 'sold', 'held', 'stop_sale', 'price_override',
    ];

    protected function casts(): array
    {
        return [
            'venue_id' => 'integer',
            'ticket_type_id' => 'integer',
            'slot_template_id' => 'integer',
            'visit_date' => 'date',
            'capacity' => 'integer',
            'sold' => 'integer',
            'held' => 'integer',
            'stop_sale' => 'boolean',
            'price_override' => 'decimal:2',
        ];
    }

    public function venue(): BelongsTo
    {
        return $this->belongsTo(EntertainmentVenue::class, 'venue_id', 'id');
    }

    public function ticketType(): BelongsTo
    {
        return $this->belongsTo(EntertainmentTicketType::class, 'ticket_type_id', 'id');
    }

    public function available(): int
    {
        return max(0, (int) $this->capacity - (int) $this->sold - (int) $this->held);
    }
}
