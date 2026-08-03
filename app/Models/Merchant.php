<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\HasApiTokens;

/**
 * Merchant authenticates via merchants table (no user_id).
 * Guards: merchant (session), merchant_api (Sanctum).
 */
class Merchant extends Authenticatable
{
    use HasApiTokens, SoftDeletes, Notifiable;

    /** @var string Auth guard for web sessions */
    protected $guard = 'merchant';

    protected $fillable = [
        'merchant_name',
        'merchant_reg_no',
        'merchant_reg_expiry_date',
        'merchant_address',
        'merchant_email',
        'merchant_mobile',
        'merchant_phone',
        'merchant_fax',
        'password',
        'created_by',
        'status',
        'allowed_service_types',
        'vat_visibility',
        'vat_refundable',
        'charge_refundable',
        'logo',
        'two_factor_type',
        'two_factor_secret',
        'two_factor_recovery_codes',
        'two_factor_confirmed_at',
    ];

    protected $hidden = [
        'password',
        'remember_token',
        'two_factor_secret',
    ];

    protected function casts(): array
    {
        return [
            'allowed_service_types' => 'array',
            'password' => 'hashed',
            'two_factor_confirmed_at' => 'datetime',
            'two_factor_recovery_codes' => 'array',
            'merchant_reg_expiry_date' => 'date',
        ];
    }

    public function getEmailAttribute(): ?string
    {
        return $this->attributes['merchant_email'] ?? null;
    }

    public function getMobileAttribute(): ?string
    {
        return $this->attributes['merchant_mobile'] ?? null;
    }

    public function getNameAttribute(): ?string
    {
        return $this->attributes['merchant_name'] ?? null;
    }

    /**
     * @param  string|array  $roles
     */
    public function hasRole($roles): bool
    {
        $list = $this->normalizeRoles($roles);

        return in_array('merchant', $list, true);
    }

    /**
     * @param  string|array  ...$roles
     */
    public function hasAnyRole(...$roles): bool
    {
        $list = [];
        foreach ($roles as $role) {
            $list = array_merge($list, $this->normalizeRoles($role));
        }

        return in_array('merchant', $list, true);
    }

    public function hasPermissionTo($permission, $guardName = null): bool
    {
        return true;
    }

    public function getProfilePicUrlAttribute(): ?string
    {
        if (empty($this->logo)) {
            return null;
        }
        $path = $this->logo;
        if (str_starts_with($path, 'avatars/') || str_starts_with($path, 'uploads/') || str_starts_with($path, 'logos/')) {
            return asset($path);
        }
        $disk = config('filesystems.profile_disk', 'public');

        return Storage::disk($disk)->url($path);
    }

    public function getEmailForPasswordReset(): string
    {
        return (string) $this->merchant_email;
    }

    /**
     * @param  string|array  $roles
     * @return list<string>
     */
    private function normalizeRoles($roles): array
    {
        if (is_array($roles)) {
            $flat = [];
            foreach ($roles as $role) {
                $flat = array_merge($flat, $this->normalizeRoles($role));
            }

            return $flat;
        }

        return array_values(array_filter(array_map('trim', explode('|', (string) $roles))));
    }

    public function hasTwoFactorEnabled(): bool
    {
        return ! empty($this->two_factor_type) && ! empty($this->two_factor_confirmed_at);
    }

    public function hasEmailTwoFactor(): bool
    {
        return $this->two_factor_type === 'email';
    }

    public function hasAuthenticatorTwoFactor(): bool
    {
        return $this->two_factor_type === 'authenticator';
    }

    public function offices(): MorphMany
    {
        return $this->morphMany(Office::class, 'official');
    }

    public function vehicles(): HasMany
    {
        return $this->hasMany(Vehicle::class, 'merchant_id', 'id');
    }

    public function cabins(): HasMany
    {
        return $this->hasMany(Cabin::class, 'marchant_id', 'id')->where('type', 'cabin');
    }

    public function seats(): HasMany
    {
        return $this->hasMany(Cabin::class, 'marchant_id', 'id')->where('type', 'seat');
    }

    public function schedules(): HasMany
    {
        return $this->hasMany(VehicleSchedule::class, 'merchant_id', 'id');
    }

    public function upcomingSchedules()
    {
        return $this->schedules()->where('schedule_date', '>=', date('Y-m-d'))->orderBy('schedule_date', 'asc');
    }

    public function bookingItems()
    {
        return $this->hasManyThrough('BookingItem', 'Vehicle', 'merchant_id', 'vehicle_id')->where('status', 1);
    }

    public function staff(): HasMany
    {
        return $this->hasMany(MerchantStaff::class, 'merchant_id', 'id');
    }

    public static function boot()
    {
        parent::boot();

        static::deleting(function (Merchant $merchant) {
            $merchant->vehicles()->each(function ($item) {
                $item->delete();
            });
        });

        static::restoring(function (Merchant $merchant) {
            $merchant->vehicles()->each(function ($item) {
                $item->restore();
            });
        });
    }
}
