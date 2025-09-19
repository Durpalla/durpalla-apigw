<?php

namespace Modules\Auth\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class UserAccessBlock extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'user_id',
        'type',
        'identity',
        'attempts',
        'blocked_at',
        'unblocked_at',
        'is_blocked',
        'audit',
        'reason',
        'remarks',
        'unblocked_by',
        'deleted_at',
    ];

    protected $casts = [
        'is_blocked' => 'boolean',
        'audit' => 'array',
        'blocked_at' => 'datetime:d/m/Y H:i:s',
        'unblocked_at' => 'datetime:d/m/Y H:i:s',
        'created_at' => 'datetime:d/m/Y H:i:s',
        'updated_at' => 'datetime:d/m/Y H:i:s'
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function unblockedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'unblocked_by');
    }

    public function scopeFilter($query, $request)
    {
        $query->where('is_blocked', true);

        if($request->filled('type')) {
            $query->where('type', $request->get('type'));
        }
        if($request->filled('keyword')) {
            $query->where('identity', $request->get('keyword'));
        }
        return $query;
    }
}
