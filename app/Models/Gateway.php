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
        'charge_percent',
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
        'charge_percent' => 'decimal:2',
    ];

    /**
     * Display/estimate gateway PG fee for a fare base (e.g. ticket fare 2000 × 2.1% = 42).
     */
    public static function estimateCost(float $fareBase, float|string|null $percent): float
    {
        $rate = max(0, (float) $percent);

        return round(max(0, $fareBase) * $rate / 100, 2);
    }

    public function resolvedChargePercent(bool $isLiveGateway = true): float
    {
        if (! $isLiveGateway) {
            return 0.0;
        }

        $configured = (float) ($this->charge_percent ?? 0);
        if ($configured > 0) {
            return $configured;
        }

        return max(0, (float) (function_exists('getOption') ? getOption('service_charge_bank', 0) : 0));
    }

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
        if ($this->media_id) {
            $media = $this->relationLoaded('media')
                ? $this->media
                : $this->media()->first();

            if ($media) {
                $path = (string) ($media->getRawOriginal('attachment') ?? '');
                if ($path !== '') {
                    return upload_asset($path) ?? asset($path);
                }
            }
        }

        return asset('default/gateway.png');
    }
}
