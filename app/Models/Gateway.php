<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Gateway extends Model
{
    protected $fillable = ['name', 'description', 'logo', 'status'];

    public function getIconAttribute(): string
    {
        return asset('default/gateway.png');
    }
}
