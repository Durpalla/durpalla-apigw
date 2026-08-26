<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\HasApiTokens;

class MerchantStaff extends Authenticatable
{
    use HasApiTokens, SoftDeletes, Notifiable, \App\Traits\Auditable;

    protected $table = 'merchant_staff';

    /** @var string Auth guard for web sessions */
    protected $guard = 'merchant_staff';

    protected $fillable = [
        'merchant_id',
        'name',
        'email',
        'mobile',
        'password',
        'type',
        'permissions',
        'designation_id',
        'counter_id',
        'status',
        'profile_pic',
        'email_verified_at',
        'device_id',
        'nid',
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
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'two_factor_confirmed_at' => 'datetime',
            'two_factor_recovery_codes' => 'array',
            'permissions' => 'array',
        ];
    }

    public function merchant(): BelongsTo
    {
        return $this->belongsTo(Merchant::class, 'merchant_id', 'id');
    }

    /**
     * Vehicle assignments via vehicle_supervisors.supervisor_id (= merchant_staff.id).
     */
    public function vehicles(): HasMany
    {
        return $this->hasMany(VehicleSupervisor::class, 'supervisor_id', 'id');
    }

    public function isSupervisor(): bool
    {
        return $this->type === 'supervisor';
    }

    /**
     * Spatie-compatible role check for Blade / middleware that call hasRole().
     * Types may be a pipe-separated string or an array.
     *
     * @param  string|array  $roles
     */
    public function hasRole($roles): bool
    {
        return in_array($this->type, $this->normalizeRoles($roles), true);
    }

    /**
     * Spatie-compatible any-role check.
     *
     * @param  string|array  ...$roles
     */
    public function hasAnyRole(...$roles): bool
    {
        $flat = [];
        foreach ($roles as $role) {
            $flat = array_merge($flat, $this->normalizeRoles($role));
        }

        return in_array($this->type, $flat, true);
    }

    /**
     * Direct permission checks for merchant desk staff.
     */
    public function hasPermissionTo($permission, $guardName = null): bool
    {
        $permissions = $this->permissionNames();
        if (in_array('*', $permissions, true)) {
            return true;
        }

        return in_array((string) $permission, $permissions, true);
    }

    /**
     * @return list<string>
     */
    public function permissionNames(): array
    {
        $raw = $this->permissions;
        if (! is_array($raw)) {
            return [];
        }

        return array_values(array_unique(array_filter(array_map(
            static fn ($value) => strtolower(trim((string) $value)),
            $raw,
        ))));
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

    public function getProfilePicUrlAttribute(): string
    {
        if (empty($this->profile_pic)) {
            return asset('default/avatar.png');
        }
        $path = $this->profile_pic;
        if (str_starts_with($path, 'avatars/') || str_starts_with($path, 'uploads/') || str_starts_with($path, 'logos/')) {
            return asset($path);
        }
        $disk = config('filesystems.profile_disk', 'public');

        return Storage::disk($disk)->url($path);
    }
}
