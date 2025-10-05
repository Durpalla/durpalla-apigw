<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Laravel\Passport\HasApiTokens;

class Agent extends Model
{
    use HasApiTokens, SoftDeletes;
    protected $table = 'users';
    protected $guard_name = 'web';
    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'name', 'email', 'password', 'mobile', 'designation_id', 'type', 'merchant_id', 'status', 'email_verified_at', 'counter_id'
    ];

    public function resolveChildRouteBinding($childType, $value, $field): ?Model
    {
        //
    }

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

    public static function boot() {
        parent::boot();
        static::deleting(function($user) {
            $user->meta()->delete();
            foreach( $user->logs() as $log ) {
                $log->delete();
            }
        });
    }
}
