<?php

namespace App\Models;

use App\Constants\AppConst;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CabinLock extends Model
{
    protected $fillable = ['cabin_id', 'trip_id', 'mapping_id', 'customer_token', 'expire_at'];

    protected $casts = [
        'expire_at' => 'datetime',
    ];

    public function mapping(): BelongsTo
    {
        return $this->belongsTo(ScheduleCabinMapping::class);
    }

    public static function boot() {
        parent::boot();
        static::deleting(function($lock) {
            $item = $lock->mapping()->update(['is_locked' => AppConst::BOOKING_ITEM_PENDING]);
        });
    }
}
