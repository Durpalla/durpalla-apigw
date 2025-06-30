<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Office extends Model
{
    protected $fillable = ['name', 'address', 'latitude', 'longitude', 'official_id', 'official_type'];
    public function official(): MorphTo
    {
        return $this->morphTo();
    }
}
