<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EntertainmentTicketEvent extends Model
{
    protected $fillable = [
        'ticket_id', 'event_type', 'actor_id', 'actor_type', 'consent_note', 'meta',
    ];

    protected function casts(): array
    {
        return [
            'ticket_id' => 'integer',
            'actor_id' => 'integer',
            'meta' => 'array',
        ];
    }

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(EntertainmentTicket::class, 'ticket_id', 'id');
    }
}
