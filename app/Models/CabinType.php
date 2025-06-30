<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CabinType extends Model
{
    protected $fillable = ['name', 'letter', 'capacity', 'is_ac', 'type', 'service_type'];
}
