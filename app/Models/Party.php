<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use App\Models\User;
use Venturecraft\Revisionable\RevisionableTrait;

class Party extends Model
{
    use RevisionableTrait;
    protected $fillable = [
        'name',
        'description',
        'officer_id',
        'user_id',
        'address',
        'domain_name',
        'slug'
    ];

    /**
     * Get all of the party's offices.
     */
    public function offices(): MorphMany
    {
        return $this->morphMany(Office::class, 'official');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function officer(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
