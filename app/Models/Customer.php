<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Http\Request;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\HasApiTokens;

/**
 * Separate customer identity – not the users table.
 * Use guard 'customer' (Sanctum) for customer API auth.
 */
class Customer extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable, SoftDeletes;

    protected $table = 'customers';

    protected $fillable = [
        'name',
        'email',
        'mobile',
        'password',
        'status',
        'profile_pic',
        'email_verified_at',
        'two_factor_enabled',
        'two_factor_confirmed_at',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'email_verified_at' => 'datetime',
            'two_factor_enabled' => 'boolean',
            'two_factor_confirmed_at' => 'datetime',
        ];
    }

    public function hasTwoFactorEnabled(): bool
    {
        return (bool) $this->two_factor_enabled && ! empty($this->two_factor_confirmed_at);
    }

    /** @var string Guard name for auth (customer). */
    protected $guard = 'customer';

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

    /**
     * Legacy user_metas still keyed by user_id until customer_metas exist.
     */
    public function meta(): HasOne
    {
        return $this->hasOne(UserMeta::class, 'user_id', 'id');
    }

    public function deviceToken()
    {
        return $this->hasMany(DeviceToken::class, 'user_id', 'id');
    }

    public function scopeFilter(Builder $query, Request $request): Builder
    {
        $keyword = $request->input('search.value') ?: $request->input('keyword');
        if ($keyword !== null && $keyword !== '') {
            $query->where(function ($q) use ($keyword) {
                $q->where('name', 'like', "%{$keyword}%")
                    ->orWhere('mobile', 'like', "%{$keyword}%")
                    ->orWhere('email', 'like', "%{$keyword}%");
            });
        }

        $status = $request->input('status');
        if ($status !== null && $status !== '') {
            $status = (int) $status;
            if ($status === 9) {
                $query->onlyTrashed();
            } else {
                $query->where('status', $status);
            }
        }

        return $query;
    }
}
