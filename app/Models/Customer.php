<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Notifications\Notifiable;

class Customer extends Model
{
    use Notifiable;
    protected $table = 'users';

    protected static function booted(): void
    {
        static::addGlobalScope('type', function (Builder $builder) {
            $builder->where('type', 'customer');
        });
    }
}
