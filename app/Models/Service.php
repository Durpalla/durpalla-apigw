<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Venturecraft\Revisionable\RevisionableTrait;

class Service extends Model
{
    use RevisionableTrait;
    protected $fillable = ['user_id', 'name', 'slug', 'description', 'status'];
}
