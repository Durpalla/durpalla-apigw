<?php

namespace App\Services;

use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Illuminate\Contracts\Auth\Authenticatable;
use PragmaRX\Google2FA\Google2FA;

class MerchantTwoFactorService
{
    public function generateSecret(): string
    {
        return (new Google2FA)->generateSecretKey(32);
    }

    public function getQRCodeUrl(Authenticatable $user, string $secret): string
    {
        return (new Google2FA)->getQRCodeUrl(
            (string) config('app.name', 'Durpalla'),
            (string) ($user->email ?? ''),
            $secret
        );
    }

    public function getQRCodeSvg(Authenticatable $user, string $secret): string
    {
        $url = $this->getQRCodeUrl($user, $secret);
        $writer = new Writer(new ImageRenderer(new RendererStyle(200), new SvgImageBackEnd));

        return $writer->writeString($url);
    }

    /**
     * Verify TOTP code from authenticator app.
     * Trims input and uses a time window of 2 (≈90s) to allow for clock drift.
     */
    public function verify(string $secret, string $code): bool
    {
        $code = trim(preg_replace('/\D/', '', (string) $code) ?? '');
        if (strlen($code) !== 6) {
            return false;
        }

        return (new Google2FA)->verifyKey($secret, $code, 2);
    }

    public function generateRecoveryCodes(int $count = 8): array
    {
        $codes = [];
        for ($i = 0; $i < $count; $i++) {
            $codes[] = strtoupper(bin2hex(random_bytes(4)));
        }

        return $codes;
    }
}
