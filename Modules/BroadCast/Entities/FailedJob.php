<?php

namespace Modules\BroadCast\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class FailedJob extends Model
{
    use HasFactory;

    protected $fillable = [];
    
    protected static function newFactory()
    {
        return \Modules\BroadCast\Database\factories\FailedJobFactory::new();
    }
}
