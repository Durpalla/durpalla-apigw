<?php

namespace Modules\Gateway\Entities;

use App\Helpers\CommonHelper;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Provider\Entities\Provider;

class Gateway extends Model
{
    use SoftDeletes;
    const ACTIVE                    = 1;
    const INACTIVE                  = 0;
    const ACTIVE_TEXT               = 'Active';
    const INACTIVE_TEXT             = 'Inactive';

    protected $fillable = [
        'name',
        'class_name',
        'status',
        'is_editable'
    ];

    public function credentials(): HasMany
    {
        return $this->hasMany(GatewayCredential::class);
    }

    public function endpoints(): HasMany
    {
        return $this->hasMany(GatewayEndpoint::class);
    }

    public function getNiceStatusAttribute(): string
    {
        return $this->status == 1 ? self::ACTIVE_TEXT : self::INACTIVE_TEXT;
    }

    public function getCreatedAtAttribute($datetime): string
    {
        return CommonHelper::parseLocalTimeZone($datetime);
    }

    public function getIsEditableAttribute($datetime): string
    {
        return $this->id !== 1 && CommonHelper::hasPermission(['gateway-update']);
    }

    public function getUpdatedAtAttribute($datetime): string
    {
        return CommonHelper::parseLocalTimeZone($datetime);
    }
}
