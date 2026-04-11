<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserOtp extends Model
{
    protected $fillable = ['mobile', 'otp_code', 'type',
        'verified',
        'updated_at'
    ];

    public function revoked(): void
    {
        self::update(['verified' => 1]);
    }
}
