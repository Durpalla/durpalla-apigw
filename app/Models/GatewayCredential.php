<?php

namespace App\Models;

use App\Helpers\CommonHelper;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Crypt;

class GatewayCredential extends Model
{
    protected $fillable = ['gateway_id', 'key', 'value'];

    public function gateway(): BelongsTo
    {
        return $this->belongsTo(Gateway::class);
    }

    public static function boot()
    {
        parent::boot();

        static::creating(function (GatewayCredential $credential) {
            $credential->value = Crypt::encrypt($credential->value);
        });
    }

    public function getCreatedAtAttribute($datetime): string
    {
        return CommonHelper::parseLocalTimeZone($datetime);
    }
}
