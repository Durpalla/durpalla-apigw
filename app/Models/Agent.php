<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\HasApiTokens;

class Agent extends Authenticatable
{
    use HasApiTokens, SoftDeletes, Notifiable;

    protected $table = 'agents';

    /** @var string Auth guard (Sanctum API) */
    protected $guard = 'agent';

    protected $fillable = [
        'name',
        'email',
        'mobile',
        'password',
        'status',
        'profile_pic',
        'email_verified_at',
        'nid',
        'device_id',
        'source',
        'legacy_partner_id',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function getProfilePicUrlAttribute(): string
    {
        if (empty($this->profile_pic)) {
            return asset('default/avatar.png');
        }
        $path = $this->profile_pic;
        if (str_starts_with($path, 'avatars/') || str_starts_with($path, 'uploads/')) {
            return asset($path);
        }
        $disk = config('filesystems.profile_disk', 'public');

        return Storage::disk($disk)->url($path);
    }

    /**
     * Legacy compatibility for `$user->type === 'agent'`.
     * Not a DB column — do not use in where('type', ...).
     */
    public function getTypeAttribute(): string
    {
        return 'agent';
    }

    /**
     * Legacy user_metas still keyed by user_id; keep until agent_metas / polymorphic.
     */
    public function meta(): HasOne
    {
        return $this->hasOne(UserMeta::class, 'user_id', 'id');
    }

    public function incentive(): HasOne
    {
        return $this->hasOne(AgentIncentive::class, 'agent_id', 'id');
    }

    public function logs(): HasMany
    {
        return $this->hasMany(ActivityLog::class, 'user_id', 'id');
    }

    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class, 'customer_id', 'id');
    }

    public function vehicles(): BelongsToMany
    {
        return $this->belongsToMany(Vehicle::class, 'agent_vehicle', 'agent_id', 'vehicle_id');
    }

    public static function boot()
    {
        parent::boot();

        static::deleting(function (Agent $agent) {
            if (! $agent->isForceDeleting()) {
                return;
            }
            $agent->meta()->delete();
            foreach ($agent->logs as $log) {
                $log->delete();
            }
        });
    }
}
