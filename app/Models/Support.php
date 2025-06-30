<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Support extends Model
{
    protected $fillable = ['user_id', 'name', 'email', 'subject', 'message'];
}
