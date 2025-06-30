<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
// use Illuminate\Database\Eloquent\SoftDeletes;

class Option extends Model
{
    protected $fillable = ['field', 'value', 'tab'];
    public $timestamps = false;
}
