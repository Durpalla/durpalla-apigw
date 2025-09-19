<?php

namespace Modules\Auth\Entities;

use App\Helpers\CommonHelper;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Notifications\Notifiable;
use Laravel\Passport\HasApiTokens;
use Modules\Activity\App\Traits\ActivityTrait;
use Spatie\Permission\Traits\HasRoles;

class User extends \App\Models\User
{
    use HasApiTokens, HasRoles, Notifiable, ActivityTrait;

    protected $fillable = ['name', 'email', 'status', 'password', 'is_editable'];
    protected string $guard_name = 'web';
    protected static $logOnlyDirty = true;

    protected static $logAttributes = ['name', 'email', 'status'];

    protected $casts = [
        'password' => 'hashed',
    ];

    public function getDescriptionForEvent(string $eventName): string
    {
        return "User {$eventName}";
    }

    public function role()
    {
        return $this->roles->first();
    }

    public function getNiceStatusAttribute()
    {
        return config('auth.user.statuses')[$this->status] ?? 'N/A';
    }

    public function getCreatedAtAttribute($datetime): string
    {
        return CommonHelper::parseLocalTimeZone($datetime);
    }

    public function getUpdatedAtAttribute($datetime): string
    {
        return CommonHelper::parseLocalTimeZone($datetime);
    }

    public function isActive(): bool
    {
        return $this->status == 1;
    }
}
