<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Storage;
use Laravel\Passport\HasApiTokens;

/**
 * Slim admin/staff user on users table (Passport).
 * Customers, merchants, agents, partners use their own tables/guards.
 */
class User extends Authenticatable
{
    use HasFactory;
    use HasApiTokens;
    use Notifiable;
    use SoftDeletes;

    protected $fillable = [
        'name',
        'email',
        'mobile',
        'password',
        'status',
        'email_verified_at',
        'profile_pic',
        'device_id',
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
        ];
    }

    /**
     * Passport matches auth.providers.users.model exactly.
     */
    public function getProviderName(): string
    {
        $passportProviders = collect(config('auth.guards'))
            ->where('driver', 'passport')
            ->pluck('provider')
            ->all();

        foreach (config('auth.providers') as $provider => $config) {
            if (
                in_array($provider, $passportProviders, true)
                && ($config['driver'] ?? null) === 'eloquent'
                && isset($config['model'])
                && is_a($this, $config['model'])
            ) {
                return $provider;
            }
        }

        if (in_array('users', $passportProviders, true)) {
            return 'users';
        }

        throw new \LogicException('Unable to determine authentication provider for this model from configuration.');
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
        if (str_starts_with($path, 'avatars/') || str_starts_with($path, 'uploads/')) {
            return asset($path);
        }
        $disk = config('filesystems.profile_disk', 'public');

        return Storage::disk($disk)->url($path);
    }

    public function deviceToken()
    {
        return $this->hasMany(DeviceToken::class);
    }

    public function meta(): HasOne
    {
        return $this->hasOne(UserMeta::class, 'user_id', 'id');
    }

    public function logs(): HasMany
    {
        return $this->hasMany(ActivityLog::class, 'user_id', 'id');
    }
}
