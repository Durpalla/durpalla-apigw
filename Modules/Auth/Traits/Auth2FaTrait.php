<?php

namespace Modules\Auth\Traits;

use Modules\Auth\Constants\AuthConstant;

trait Auth2FaTrait
{
    public function generateToken(string $string): string
    {
        return encrypt($string);
    }

    public function decryptToken(string $token): string
    {
        return decrypt($token);
    }

    public function newOtp(): int|string
    {
        return (app()->environment('local')) ? AuthConstant::DEFAULT_OTP : mt_rand(111111, 999999);
    }
}
