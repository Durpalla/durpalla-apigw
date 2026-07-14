<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Models\User;

class SocialPoster extends Model
{
    protected $fillable = ['name', 'description', 'vehicle_schedule_id', 'merchant_id', 'vehicle_id', 'user_id', 'launch_name', 'route_name', 'poster', 'share_count'];

    public function VehicleSchedule()
    {
        return $this->belongsTo(VehicleSchedule::class, 'vehicle_schedule_id', 'id')->withTrashed();
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function merchant(): BelongsTo
    {
        return $this->belongsTo(Merchant::class, 'merchant_id', 'id');
    }

    public function launch(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class);
    }
}
