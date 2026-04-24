<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Models\User;

class Coupon extends Model
{
    protected $fillable = [
        'name',
        'home_title',
        'home_subtitle',
        'home_sort_order',
        'link_slug',
        'external_url',
        'show_on_home',
        'code',
        'is_cabin',
        'is_seat',
        'is_deck',
        'is_offer',
        'type',
        'discount_type',
        'discount_amount',
        'user_id',
        'poster',
        'offer_start',
        'offer_end',
        'items',
        'status',
    ];

    public function mappings(): HasMany
    {
    	return $this->hasMany(CouponMapping::class, 'coupon_id', 'id');
    }

    public function user(): BelongsTo
    {
    	return $this->belongsTo(User::class, 'user_id', 'id');
    }

    public static function boot() {
        parent::boot();
        static::deleting(function($vehicle) {

        });
    }
}
