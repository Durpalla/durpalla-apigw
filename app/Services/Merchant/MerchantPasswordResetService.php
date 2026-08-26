<?php

namespace App\Services\Merchant;

use App\Models\Merchant;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Modules\Auth\Traits\Auth2FaTrait;

class MerchantPasswordResetService
{
    use Auth2FaTrait;

    public function requestOtp(string $email): array
    {
        $user = $this->findMerchantByEmail($email);
        if (!$user) {
            return ['success' => false, 'message' => 'Account not found.', 'status' => 404];
        }
        if ((int) $user->status !== 1) {
            return ['success' => false, 'message' => 'Account is inactive.', 'status' => 403];
        }
        if (!$user->merchant_email) {
            return ['success' => false, 'message' => 'This account has no email configured.', 'status' => 422];
        }

        $this->createResetOtp($user->merchant_email);

        return [
            'success' => true,
            'message' => App::environment('local', 'development')
                ? 'OTP sent to email (dev default may be 111111).'
                : 'OTP sent to your email.',
            'token' => $this->generateToken($user->merchant_email),
            'status' => 200,
        ];
    }

    public function verifyOtp(string $token, string $code): array
    {
        $email = $this->resolveEmailFromToken($token);
        if ($email === null) {
            return ['success' => false, 'message' => 'Invalid token.', 'status' => 422];
        }

        $user = $this->findMerchantByEmail($email);
        if (!$user) {
            return ['success' => false, 'message' => 'Account not found.', 'status' => 404];
        }

        if (!$this->verifyResetOtp($email, $code)) {
            return ['success' => false, 'message' => 'Invalid or expired code.', 'status' => 422];
        }

        $this->revokeResetOtps($email);
        Cache::put($this->verifiedCacheKey($email), true, now()->addMinutes(5));

        return [
            'success' => true,
            'message' => 'OTP verified.',
            'token' => $this->generateToken($email),
            'status' => 200,
        ];
    }

    public function resetPassword(string $token, string $password): array
    {
        $email = $this->resolveEmailFromToken($token);
        if ($email === null) {
            return ['success' => false, 'message' => 'Invalid token.', 'status' => 422];
        }

        $user = $this->findMerchantByEmail($email);
        if (!$user) {
            return ['success' => false, 'message' => 'Account not found.', 'status' => 404];
        }

        if (! Cache::get($this->verifiedCacheKey($email))) {
            return ['success' => false, 'message' => 'Verify OTP before resetting password.', 'status' => 422];
        }

        $user->password = Hash::make($password);
        $user->save();

        Cache::forget($this->verifiedCacheKey($email));
        $this->revokeResetOtps($email);

        return [
            'success' => true,
            'message' => 'Password reset successfully.',
            'status' => 200,
        ];
    }

    private function resolveEmailFromToken(string $token): ?string
    {
        try {
            $email = trim((string) $this->decryptToken($token));
            return $email !== '' ? $email : null;
        } catch (\Throwable) {
            return null;
        }
    }

    private function findMerchantByEmail(string $email): ?Merchant
    {
        return Merchant::query()
            ->where('merchant_email', trim($email))
            ->first();
    }

    private function verifiedCacheKey(string $email): string
    {
        return 'merchant_pwd_reset_verified:' . strtolower(trim($email));
    }
}
