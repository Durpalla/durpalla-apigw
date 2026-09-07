<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BoatTripPackageStop extends Model
{
    protected $fillable = [
        'package_id',
        'stoppage_id',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'package_id' => 'integer',
            'stoppage_id' => 'integer',
            'sort_order' => 'integer',
        ];
    }

    public function package(): BelongsTo
    {
        return $this->belongsTo(BoatTripPackage::class, 'package_id', 'id');
    }

    public function stoppage(): BelongsTo
    {
        return $this->belongsTo(BoatStoppage::class, 'stoppage_id', 'id');
    }
}
