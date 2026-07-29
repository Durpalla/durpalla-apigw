<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class AgentReferredMerchantDocument extends Model
{
    protected $fillable = [
        'referred_merchant_id',
        'type',
        'path',
    ];

    public function referredMerchant(): BelongsTo
    {
        return $this->belongsTo(AgentReferredMerchant::class, 'referred_merchant_id');
    }

    public function getUrlAttribute(): string
    {
        $disk = config('filesystems.profile_disk', 'public');

        return Storage::disk($disk)->url($this->path);
    }
}
