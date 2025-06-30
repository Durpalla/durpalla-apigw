<?php

namespace Modules\BroadCast\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\User;

class BroadCast extends Model
{
    protected $fillable = ['user_id', 'title', 'type', 'group', 'message', 'customers', 'scheduled_at', 'attachment'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
