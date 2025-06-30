<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserMeta extends Model
{
    public $timestamps = false;
    protected $fillable = ['officer_id', 'user_id', 'officer_designation', 'address', 'nid_no', 'nid_photo', 'trade_license', 'trade_license_photo', 'nid_visible_until', 'nid_verified'];
}
