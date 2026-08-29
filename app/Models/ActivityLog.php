<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ActivityLog extends Model
{
    protected $fillable = [
        'subject', 'url', 'method', 'ip', 'agent', 'user_id',
    ];
}
