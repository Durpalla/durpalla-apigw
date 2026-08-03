<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Gateway extends Model
{
    protected $fillable = [
        'name',
        'description',
        'logo',
        'status',
        'class_name',
        'type',
        'code',
        'channel',
        'merchant_id',
        'for_public',
        'for_agent',
        'for_merchant',
        'requires_trx',
        'sort_order',
        'media_id',
        'is_editable',
    ];

    protected $casts = [
        'for_public' => 'boolean',
        'for_agent' => 'boolean',
        'for_merchant' => 'boolean',
        'requires_trx' => 'boolean',
        'status' => 'integer',
        'sort_order' => 'integer',
        'merchant_id' => 'integer',
    ];

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

    public function media(): BelongsTo
    {
        return $this->belongsTo(Media::class, 'media_id');
    }

    public function getIconAttribute(): string
    {
        if ($this->media_id && $this->media) {
            return $this->media->publicUrl();
        }

        return asset('default/gateway.png');
    }
}
