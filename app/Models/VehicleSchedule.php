<?php

namespace App\Models;

use App\Constants\AppConst;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\User;


class VehicleSchedule extends Model
{
    use SoftDeletes;
    protected $fillable = [
        'route_id',
        'vehicle_id',
        'merchant_id',
        'schedule_date',
        'schedule_type',
        'starting_point',
        'ending_point',
        'leaving_at',
        'vehicle_schedule_id',
        'user_id',
        'status',
        'operation_hour',
        'operation_timeline'
    ];

    protected $casts = [
        'schedule_date' => 'datetime:Y-m-d H:i:s',
        'created_at' => 'datetime:Y-m-d H:i:s',
        'updated_at' => 'datetime:Y-m-d H:i:s',
    ];
    const CANCEL = "CANCEL";
    const RESCHEDULE = "RESCHEDULE";

    public function scopeActive($q)
    {
        return $q->where('schedule_date', '>=', date('Y-m-d'))->where('status', AppConst::SCHEDULE_ACTIVE);
    }

    public function getActive() {
        return $this->active()->get();
    }

    public function route(): BelongsTo
    {
    	return $this->belongsTo(VehicleRoute::class);
    }

    public function mappings(): HasMany
    {
        return $this->hasMany(ScheduleCabinMapping::class, 'schedule_id', 'id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function seatMappings(): HasMany
    {
        return $this->mappings()->where('type', 'seat');
    }

    public function cabinMappings(): HasMany
    {
        return $this->mappings()->where('type', 'cabin');
    }

    public function discounts(): HasMany
    {
        return $this->hasMany(Discount::class, 'schedule_id', 'id')->where('status', 1);
    }

    public function launch(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class, 'vehicle_id', 'id');
    }

    public function vehicle()
    {
        return $this->belongsTo(Vehicle::class)->withTrashed();
    }

    public function merchant(): BelongsTo
    {
        return $this->belongsTo(Merchant::class, 'merchant_id', 'id');
    }

    public function routeProperties(): HasMany
    {
        return $this->hasMany(RouteProperty::class, 'route_id', 'route_id');
    }

    public function startFrom(): BelongsTo
    {
        return $this->belongsTo(Ghat::class, 'starting_point', 'id');
    }

    public function stopTo(): BelongsTo
    {
        return $this->belongsTo(Ghat::class, 'ending_point', 'id');
    }

    /**
     * Departure → arrival for this schedule (uses starting_point / ending_point, which are
     * already chosen per trip type: straight/up vs reverse/down). Do not swap again here.
     */
    public function tripDirectionRouteLine(string $separator = ' -> '): string
    {
        $from = trim((string) ($this->startFrom?->name ?? $this->startingPoint?->ghat?->name ?? ''));
        $to = trim((string) ($this->stopTo?->name ?? $this->endingPoint?->ghat?->name ?? ''));
        if ($from === '' && $to === '') {
            return '';
        }
        if ($from === '') {
            return $to;
        }
        if ($to === '') {
            return $from;
        }

        return $from.$separator.$to;
    }

    public function boardingVias(): HasMany
    {
        return $this->hasMany(RouteProperty::class, 'route_id', 'route_id')->where('type', 'via')->orderBy('serial_num', 'ASC');
    }

    public function startingPoint(): HasOne
    {
        return $this->hasOne(RouteProperty::class, 'route_id', 'route_id')->where('type', 'start');
    }

    public function endingPoint(): HasOne
    {
        return $this->hasOne(RouteProperty::class, 'route_id', 'route_id')->where('type', 'end');
    }

    public function cabins(): HasMany
    {
        return $this->hasMany(Cabin::class, 'vehicle_id', 'vehicle_id')->where('type', 'cabin');
    }

    public function seats(): HasMany
    {
        return $this->hasMany(Cabin::class, 'vehicle_id', 'vehicle_id')->where('type', 'seat');
    }

    public function sofas(): HasMany
    {
        return $this->hasMany(Cabin::class, 'vehicle_id', 'vehicle_id')->where('type', 'sofa');
    }

    public function cabinCount(): int
    {
        return $this->cabins()->count();
    }

    public function seatCount(): int
    {
        return $this->seats()->count();
    }

    public function locks(): HasMany
    {
        return $this->hasMany(CabinLock::class, 'trip_id', 'id');
    }

    public function booking(): HasManyThrough
    {
        return $this->hasManyThrough(Booking::class, BookingItem::class, 'id', 'booking_items.booking_id');
    }

    public function bookingItems(): HasMany
    {
        return $this->hasMany(BookingItem::class, 'trip_id', 'id')->where('status', 1);
    }

    public function bookingConfirmed(): HasMany
    {
        return $this->hasMany(BookingItem::class, 'trip_id', 'id')->where('status', 1);
    }

    public function cabinBookings(): HasMany
    {
        return $this->bookingItems()->where('booking_type', 'cabin');
    }

    public function cabinBookingCount(): int
    {
        return $this->bookingItems()->where('booking_type', 'cabin')->count();
    }

    public function seatBookings(): HasMany
    {
        return $this->bookingItems()->where('booking_type', 'seat');
    }

    public function seatBookingCount(): int
    {
        return $this->seatBookings()->where('booking_type', 'seat')->count();
    }

    public function ticketBookings(): HasMany
    {
        return $this->bookingItems()->where('booking_type', 'deck');
    }

    public function ticketBookingCount(): HasMany
    {
        return $this->bookingItems()->where('booking_type', 'deck');
    }

    public function deckFares(): HasMany
    {
        return $this->hasMany(DeckFare::class, 'route_id', 'route_id')->active();
    }

    public function decks(): HasMany
    {
        return $this->hasMany(DeckFare::class, 'vehicle_id', 'vehicle_id')->active();
    }

    // automatically deleted relations
    public static function boot() {
        parent::boot();
        static::deleting(function($schedule) {
            foreach( $schedule->mappings() as $mapping ) {
                $mapping->delete();
            }

            foreach( $schedule->bookingItems() as $item ) {
                $item->delete();
            }

            foreach( $schedule->routeProperties() as $property ) {
                $property->delete();
            }
        });
    }
}
