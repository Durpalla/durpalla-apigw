<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class City extends Model
{
    protected $fillable = [
        'name',
        'code',
        'country_id',
        'country',
        'state',
        'latitude',
        'longitude',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'latitude' => 'decimal:8',
        'longitude' => 'decimal:8',
    ];

    public function countryRelation()
    {
        return $this->belongsTo(Country::class, 'country_id', 'id');
    }

    public function hotels()
    {
        return $this->hasMany(Hotel::class, 'city_id', 'id');
    }

    /** Country name (from relation or legacy country field). */
    public function getCountryNameAttribute(): string
    {
        if ($this->countryRelation) {
            return $this->countryRelation->name;
        }
        return (string) ($this->attributes['country'] ?? '');
    }
}
