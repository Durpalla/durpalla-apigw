<?php

namespace App\Traits;

use Illuminate\Support\Facades\DB;
use App\Constants\AuthConstant;
use App\Models\Otp;

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
        return app()->environment(['local', 'development']) ? AuthConstant::DEFAULT_OTP : mt_rand(111111, 999999);
    }

    /**
     * Invalidate prior login OTPs and create a fresh one (avoids matching an older code).
     */
    protected function createLoginOtp(string $email): Otp
    {
        $this->revokeLoginOtps($email);

        return Otp::create([
            'type' => AuthConstant::LOGIN_OTP_TYPE,
            'email' => $email,
            'code' => (string) $this->newOtp(),
        ]);
    }

    protected function verifyLoginOtp(string $email, string $code): bool
    {
        $normalized = preg_replace('/\D/', '', $code);
        if (strlen($normalized) !== 6) {
            return false;
        }

        $otp = Otp::query()
            ->where('email', $email)
            ->where('type', AuthConstant::LOGIN_OTP_TYPE)
            ->where('revoked', false)
            ->where('updated_at', '>=', now()->subMinutes(5))
            ->latest('id')
            ->first();

        return $otp !== null && (string) $otp->code === $normalized;
    }

    protected function revokeLoginOtps(string $email): void
    {
        DB::table('otps')
            ->where('email', $email)
            ->where('type', AuthConstant::LOGIN_OTP_TYPE)
            ->where('revoked', false)
            ->update(['revoked' => true, 'updated_at' => now()]);
    }

    protected function createResetOtp(string $email): Otp
    {
        $this->revokeResetOtps($email);

        return Otp::create([
            'type' => AuthConstant::RESET_OTP_TYPE,
            'email' => $email,
            'code' => (string) $this->newOtp(),
        ]);
    }

    protected function verifyResetOtp(string $email, string $code): bool
    {
        $normalized = preg_replace('/\D/', '', $code);
        if (strlen($normalized) !== 6) {
            return false;
        }

        $otp = Otp::query()
            ->where('email', $email)
            ->where('type', AuthConstant::RESET_OTP_TYPE)
            ->where('revoked', false)
            ->where('updated_at', '>=', now()->subMinutes(5))
            ->latest('id')
            ->first();

        return $otp !== null && (string) $otp->code === $normalized;
    }

    protected function revokeResetOtps(string $email): void
    {
        DB::table('otps')
            ->where('email', $email)
            ->where('type', AuthConstant::RESET_OTP_TYPE)
            ->where('revoked', false)
            ->update(['revoked' => true, 'updated_at' => now()]);
    }
}
