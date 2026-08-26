<?php

namespace App\Models\SeatLayout;

use Illuminate\Database\Eloquent\Model;

class CabinLayout extends Model
{
    protected $fillable = [
        'name',
        'code',
        'vehicle_type',
        'total_cabins',
        'floors',
        'layout_config',
        'description',
        'is_active',
    ];

    protected $casts = [
        'total_cabins' => 'integer',
        'floors' => 'integer',
        'layout_config' => 'array',
        'is_active' => 'boolean',
    ];
}
