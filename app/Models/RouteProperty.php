<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RouteProperty extends Model
{
    public function ghat(): BelongsTo
    {
    	return $this->belongsTo(Ghat::class, 'ghat_id', 'id')->withTrashed();
    }
}
