<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Blog extends Model
{

    protected $fillable = ['id', 'title', 'body'];
    public function blogcatagory()
    {
    	return $this->hasOne(BlogCatagory::class, 'id', 'catagory_id');
    }
}
