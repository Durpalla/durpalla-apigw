<?php

namespace App\Models;

use App\Models\Merchant;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Promotion extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'name', 'code', 'description',
        'discount_type', 'discount_value', 'max_discount_amount', 'min_spend_amount', 'currency',
        'service_type', 'applicable_item_types', 'channel',
        'usage_limit_total', 'usage_limit_per_user', 'redeemed_count',
        'stackable', 'priority',
        'merchant_id', 'funded_by', 'created_by', 'approval_status', 'approved_by', 'approved_at',
        'starts_at', 'ends_at', 'status',
        'poster', 'is_offer', 'home_title', 'home_subtitle', 'home_sort_order', 'link_slug', 'external_url', 'show_on_home',
    ];

    protected $casts = [
        'applicable_item_types' => 'array',
        'stackable' => 'boolean',
        'is_offer' => 'boolean',
        'show_on_home' => 'boolean',
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
        'approved_at' => 'datetime',
        'discount_value' => 'float',
        'max_discount_amount' => 'float',
        'min_spend_amount' => 'float',
    ];

    public const SERVICE_ALL = 'all';

    public function targets(): HasMany
    {
        return $this->hasMany(PromotionTarget::class);
    }

    public function redemptions(): HasMany
    {
        return $this->hasMany(PromotionRedemption::class);
    }

    public function merchant(): BelongsTo
    {
        return $this->belongsTo(Merchant::class, 'merchant_id', 'id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by', 'id');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by', 'id');
    }

    public function isCodeBased(): bool
    {
        return ! empty($this->code);
    }

    public function isAutoApplied(): bool
    {
        return empty($this->code);
    }

    public function isCurrentlyActive(): bool
    {
        $now = now();

        return $this->status === 'active'
            && $this->approval_status === 'approved'
            && $this->starts_at <= $now
            && $this->ends_at >= $now;
    }

    public function targetsFor(string $type): array
    {
        return $this->targets->where('target_type', $type)->pluck('target_id')->all();
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'active')
            ->where('approval_status', 'approved')
            ->where('starts_at', '<=', now())
            ->where('ends_at', '>=', now());
    }

    public function scopeAutoApplied($query)
    {
        return $query->whereNull('code');
    }

    public function scopeCodeBased($query)
    {
        return $query->whereNotNull('code');
    }
}
