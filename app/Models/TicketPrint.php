<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TicketPrint extends Model
{
    protected $fillable = ['booking_id', 'booking_item_id', 'supervisor_id'];
}
