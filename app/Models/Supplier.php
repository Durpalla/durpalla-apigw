<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Supplier extends Model
{
    protected $fillable = [
        'name',
        'code',
        'domain',
        'type',
        'is_active',
        'config',
        'created_by',
        'updated_by',
    ];

    /** Domain: hotel | transport (Launch/Bus/Train/Boat/Air). */
    public const DOMAIN_HOTEL = 'hotel';
    public const DOMAIN_TRANSPORT = 'transport';

    protected $casts = [
        'is_active' => 'boolean',
        'config' => 'array',
    ];

    public function bookings()
    {
        return $this->hasMany(\App\Models\Booking::class, 'supplier_id', 'id');
    }
}
