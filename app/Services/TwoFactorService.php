<?php

namespace App\Services;

use App\Constants\AppConst;
use App\Models\Customer;
use App\Models\UserOtp;
use App\Notifications\EmailOtp;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Illuminate\Support\Facades\App;
use PragmaRX\Google2FA\Google2FA;

/**
 * Customer two-factor authentication.
 *
 * Supported factors: 'email' (one-time code by mail) and 'totp'
 * (Google Authenticator or any RFC 6238 app). SMS is not supported.
 */
class TwoFactorService
{
    public const METHOD_EMAIL = 'email';

    public const METHOD_TOTP = 'totp';

    /** Minutes an emailed code stays valid. */
    private const EMAIL_CODE_TTL = 15;

    public function methods(): array
    {
        return [self::METHOD_EMAIL, self::METHOD_TOTP];
    }

    public function normalizeMethod(?string $method): string
    {
        return $method === self::METHOD_TOTP ? self::METHOD_TOTP : self::METHOD_EMAIL;
    }

    /**
     * Email a one-time code and record it for later verification.
     *
     * @param  string  $type  UserOtp type: '2fa' when enabling, '2fa_login' at login.
     */
    public function sendEmailCode(Customer $user, string $type): bool
    {
        if (empty($user->email)) {
            return false;
        }

        $otp = UserOtp::firstOrNew(['mobile' => $user->mobile]);
        $otp->mobile = $user->mobile;
        $otp->otp_code = $this->code();
        $otp->verified = 0;
        $otp->type = $type;
        $otp->attempts = ($otp->attempts ?? 0) + 1;
        $otp->updated_at = now();
        $otp->save();

        // Sync so the code reaches the inbox even when workers are idle.
        $user->notifyNow(new EmailOtp($otp->otp_code));

        return true;
    }

    /**
     * @param  array<int, string>  $types  Accepted UserOtp types.
     */
    public function verifyEmailCode(Customer $user, string $code, array $types): bool
    {
        $otp = UserOtp::where('mobile', $user->mobile)
            ->where('otp_code', $code)
            ->whereIn('type', $types)
            ->latest()
            ->first();

        if (! $otp) {
            return false;
        }
        if (strtotime((string) $otp->updated_at) < time() - (self::EMAIL_CODE_TTL * 60)) {
            return false;
        }

        $otp->verified = 1;
        $otp->save();

        return true;
    }

    public function generateSecret(): string
    {
        return (new Google2FA)->generateSecretKey(32);
    }

    public function verifyTotp(?string $secret, string $code): bool
    {
        if (empty($secret)) {
            return false;
        }

        // window=1 tolerates one 30s step of clock drift either way.
        return (new Google2FA)->verifyKey($secret, preg_replace('/\D/', '', $code) ?? '', 1);
    }

    public function otpauthUrl(Customer $user, string $secret): string
    {
        $issuer = (string) config('app.name', 'Durpalla');
        $account = $user->email ?: $user->mobile;

        return (new Google2FA)->getQRCodeUrl($issuer, (string) $account, $secret);
    }

    /** Inline SVG data URI so clients can render the QR without another request. */
    public function qrDataUri(string $otpauthUrl): string
    {
        $writer = new Writer(new ImageRenderer(new RendererStyle(240), new SvgImageBackEnd));

        return 'data:image/svg+xml;base64,'.base64_encode($writer->writeString($otpauthUrl));
    }

    private function code(): string
    {
        return App::environment('production')
            ? (string) mt_rand(100000, 999999)
            : (string) AppConst::DEFAULT_OTP;
    }
}
