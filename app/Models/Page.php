<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Page extends Model
{
    protected $fillable = ['id', 'title', 'slug', 'content'];

    public function format(): array
    {
        return $this->only(['id', 'title', 'slug', 'content']);
    }
}
