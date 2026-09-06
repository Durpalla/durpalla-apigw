<?php

namespace Tests\Unit;

use App\Support\PasswordVerifier;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class PasswordVerifierTest extends TestCase
{
    public function test_accepts_bcrypt_hash(): void
    {
        $hash = Hash::make('secret');

        $this->assertTrue(PasswordVerifier::check('secret', $hash));
        $this->assertFalse(PasswordVerifier::check('wrong', $hash));
    }

    public function test_non_bcrypt_value_returns_false_instead_of_throwing(): void
    {
        $this->assertFalse(PasswordVerifier::check('password', 'not-a-hash'));
        $this->assertFalse(PasswordVerifier::check('password', '$2y$10$invalid'));
        $this->assertFalse(PasswordVerifier::check('password', ''));
    }

    public function test_verifies_argon_hash_when_default_driver_is_bcrypt(): void
    {
        if (! defined('PASSWORD_ARGON2ID')) {
            $this->markTestSkipped('Argon2id is not available');
        }

        $hash = password_hash('secret', PASSWORD_ARGON2ID);

        $this->assertTrue(PasswordVerifier::check('secret', $hash));
        $this->assertFalse(PasswordVerifier::check('wrong', $hash));
    }
}
