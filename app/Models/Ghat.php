<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Venturecraft\Revisionable\RevisionableTrait;

class Ghat extends Model
{
    use SoftDeletes, RevisionableTrait;
	protected $fillable = ['name', 'user_id', 'latitude', 'longitude', 'altitude', 'service_type'];

    public static function boot(): void
    {
        parent::boot();
        static::creating(function ($model) {
            $model->user_id = auth()->id();
        });
    }
}
