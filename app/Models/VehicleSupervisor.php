<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Models\User;
use Venturecraft\Revisionable\RevisionableTrait;

class VehicleSupervisor extends Model
{
    use RevisionableTrait;
    protected $fillable = ['vehicle_id', 'supervisor_id', 'user_id', 'supervisor_incentive', 'incentive_type', 'is_master', 'master_id'];
    public function launch(): BelongsTo
    {
    	return $this->belongsTo(Vehicle::class, 'vehicle_id', 'id');
    }

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class);
    }

    public function user(): BelongsTo
    {
    	return $this->belongsTo(User::class, 'supervisor_id', 'id');
    }

    public function assignator(): BelongsTo
    {
    	return $this->belongsTo(User::class, 'user_id', 'id');
    }

    public function master(): BelongsTo
    {
        return $this->belongsTo(User::class, 'master_id', 'id');
    }
}
