<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BlogCatagory extends Model
{
    protected $fillable = ['id', 'title'];

    public static function boot() {
        parent::boot();
        static::deleting(function($vehicle) {

        });
    }
}
