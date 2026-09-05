<?php

namespace App\Models;

use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Http\Request;

/**
 * Shared hotels table (merchant desk + customer search).
 * Merchant fields (merchant_id, city_id, rooms/images) are primary;
 * legacy customer relations (photos/reviews/roomTypes) remain when those tables exist.
 */
class Hotel extends Model
{
    use SoftDeletes, Auditable;

    protected $fillable = [
        'merchant_id',
        'name',
        'slug',
        'city',
        'city_id',
        'address',
        'lat',
        'lng',
        'star_rating',
        'aggregate_rating',
        'review_count',
        'description',
        'policies',
        'rating',
        'check_in_time',
        'check_out_time',
        'status',
        'is_approved',
        'accepts_extra_bed',
        'max_extra_beds',
        'external_id',
        'source',
        'supplier_meta',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'merchant_id' => 'integer',
            'lat' => 'decimal:7',
            'lng' => 'decimal:7',
            'aggregate_rating' => 'decimal:2',
            'rating' => 'decimal:1',
            'status' => 'integer',
            'is_approved' => 'boolean',
            'accepts_extra_bed' => 'boolean',
            'max_extra_beds' => 'integer',
            'supplier_meta' => 'array',
        ];
    }

    public function city(): BelongsTo
    {
        return $this->belongsTo(City::class, 'city_id', 'id');
    }

    public function merchant(): BelongsTo
    {
        return $this->belongsTo(Merchant::class, 'merchant_id', 'id');
    }

    public function merchantOwner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'merchant_id', 'id');
    }

    public function rooms(): HasMany
    {
        return $this->hasMany(HotelRoom::class, 'hotel_id', 'id');
    }

    public function activeRooms(): HasMany
    {
        return $this->hasMany(HotelRoom::class, 'hotel_id', 'id')->where('status', 1);
    }

    public function facilities(): BelongsToMany
    {
        return $this->belongsToMany(HotelFacility::class, 'hotel_facility_hotel', 'hotel_id', 'hotel_facility_id');
    }

    public function images(): HasMany
    {
        return $this->hasMany(HotelImage::class, 'hotel_id', 'id')->orderBy('sort_order');
    }

    public function childPolicies(): HasMany
    {
        return $this->hasMany(HotelChildPolicy::class, 'hotel_id', 'id');
    }

    public function stopSales(): HasMany
    {
        return $this->hasMany(HotelStopSale::class, 'hotel_id', 'id');
    }

    /**
     * Keep the Extra Bed facility pivot in sync with accepts_extra_bed.
     */
    public function syncExtraBedFacility(): void
    {
        $facility = HotelFacility::query()->where('code', 'extra_bed')->first();
        if (! $facility) {
            return;
        }

        if ($this->accepts_extra_bed) {
            $this->facilities()->syncWithoutDetaching([$facility->id]);
        } else {
            $this->facilities()->detach($facility->id);
        }
    }

    public function descriptions(): HasMany
    {
        return $this->hasMany(HotelDescription::class, 'hotel_id', 'id');
    }

    public function policies(): HasMany
    {
        return $this->hasMany(HotelPolicy::class, 'hotel_id', 'id');
    }

    public function contact(): HasOne
    {
        return $this->hasOne(HotelContact::class, 'hotel_id', 'id');
    }

    public function location(): HasOne
    {
        return $this->hasOne(HotelLocation::class, 'hotel_id', 'id');
    }

    public function bookingItems(): HasMany
    {
        return $this->hasMany(BookingHotelItem::class, 'hotel_id', 'id');
    }

    public function inventory(): HasMany
    {
        return $this->hasMany(HotelRoomInventory::class, 'hotel_id', 'id');
    }

    /** Legacy customer-catalog photos table (hotel_photos). */
    public function photos(): HasMany
    {
        return $this->hasMany(HotelPhoto::class)->orderBy('sort_order');
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(HotelReview::class)->orderByDesc('reviewed_at');
    }

    public function roomTypes(): HasMany
    {
        return $this->hasMany(HotelRoomType::class);
    }

    public function scopeFilter(Builder $query, Request $request): Builder
    {
        $search = $request->input('search');
        if (is_array($search)) {
            $search = $search['value'] ?? '';
        }

        $keyword = trim((string) ($request->input('keyword') ?: $search));
        if ($keyword !== '') {
            $query->where(function (Builder $q) use ($keyword) {
                $q->where('name', 'like', '%'.$keyword.'%')
                    ->orWhere('address', 'like', '%'.$keyword.'%')
                    ->orWhereHas('city', function (Builder $cityQuery) use ($keyword) {
                        $cityQuery->where('name', 'like', '%'.$keyword.'%');
                    });
            });
        }

        if ($request->has('status') && $request->input('status') !== '') {
            $query->where('status', (int) $request->input('status'));
        }

        $type = $request->input('type', $request->input('source'));
        if ($type !== null && $type !== '') {
            $query->where('source', $type);
        }

        $star = $request->input('star', $request->input('rating'));
        if ($star !== null && $star !== '') {
            $query->where('rating', (float) $star);
        }

        if ($request->filled('city_id')) {
            $query->where('city_id', (int) $request->city_id);
        }

        if ($request->filled('merchant_id')) {
            $query->where('merchant_id', (int) $request->merchant_id);
        }

        $onboardingAgentId = $request->input('onboarding_agent_id', $request->input('agent_id'));
        if ($onboardingAgentId !== null && $onboardingAgentId !== '') {
            $query->whereHas('merchant', function (Builder $merchantQuery) use ($onboardingAgentId) {
                $merchantQuery->where('created_by', (int) $onboardingAgentId);
            });
        }

        return $query;
    }
}
