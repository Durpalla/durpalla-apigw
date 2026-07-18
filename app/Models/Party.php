<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class Party extends Model
{
    public const TYPE_SYSTEM = 'system';
    public const TYPE_AGENCY = 'agency';
    public const TYPE_API_PARTNER = 'api_partner';

    protected $fillable = [
        'name',
        'description',
        'officer_id',
        'type',
        'email',
        'mobile',
        'status',
        'address',
        'domain_name',
        'slug',
    ];

    protected $casts = [
        'status' => 'integer',
    ];

    public function offices(): MorphMany
    {
        return $this->morphMany(Office::class, 'official');
    }

    /**
     * Passport OAuth clients (client-credentials) owned by this party.
     */
    public function apiClients(): MorphMany
    {
        return $this->morphMany(\Laravel\Passport\Client::class, 'owner');
    }

    public function wallet(): HasOne
    {
        return $this->hasOne(ResellerWallet::class, 'party_id');
    }

    public function contracts(): HasMany
    {
        return $this->hasMany(ResellerContract::class, 'party_id');
    }

    public function officer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'officer_id');
    }

    public function isApiPartner(): bool
    {
        return $this->type === self::TYPE_API_PARTNER;
    }

    /**
     * Reseller's configured share (%) of Durpalla's own commission.
     */
    public function commissionSharePercent(): float
    {
        $contract = $this->contracts()
            ->where('status', 1)
            ->orderByDesc('effective_from')
            ->orderByDesc('id')
            ->first();

        return (float) ($contract->commission_share_percent ?? 0);
    }
}
