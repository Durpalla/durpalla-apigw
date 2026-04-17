<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Gateway extends Model
{
    protected $fillable = ['name', 'description', 'logo', 'status', 'class_name', 'type', 'is_editable'];

    public function credentials(): HasMany
    {
        return $this->hasMany(GatewayCredential::class);
    }

    public function params(): HasMany
    {
        return $this->hasMany(GatewayParam::class);
    }

    public function endpoints(): HasMany
    {
        return $this->hasMany(GatewayEndpoint::class);
    }

    public function getIconAttribute(): string
    {
        return asset('default/gateway.png');
    }
}
