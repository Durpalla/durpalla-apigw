<?php

namespace App\Support;

use Illuminate\Support\Facades\Hash;

/**
 * Hash::check() uses the configured hasher (bcrypt by default) and throws
 * RuntimeException when the stored hash uses another algorithm or is truncated.
 * password_verify() detects the algorithm from the hash itself.
 */
class PasswordVerifier
{
    public static function check(string $plain, string $hash): bool
    {
        if ($plain === '' || $hash === '') {
            return false;
        }

        try {
            if (Hash::isHashed($hash)) {
                return Hash::check($plain, $hash);
            }
        } catch (\Throwable) {
            // Driver/algorithm mismatch (e.g. bcrypt hasher + argon/legacy hash).
        }

        return password_verify($plain, $hash);
    }
}
