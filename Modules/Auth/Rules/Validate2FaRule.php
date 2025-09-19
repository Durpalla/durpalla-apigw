<?php

namespace Modules\Auth\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Facades\Schema;
use Modules\Auth\Entities\Otp;
use Modules\Auth\Traits\Auth2FaTrait;

class Validate2FaRule implements ValidationRule
{
    use Auth2FaTrait;

    private string $start;

    public function __construct()
    {
        $this->start = now()->subMinutes(5)->format('Y-m-d H:i:s');
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        try {
            if (!Schema::hasTable('otps')) {
                $fail('Sorry! application not configured properly.');
            }

            $otp = Otp::where('reference', request()->input('token'))->first();

            if (!$otp) {
                $fail('otp', 'Your OTP is invalid');
            }

            if ($otp->code != request()->input('code')) {
                $fail('otp', 'Your otp does not match.');
            }

            if ($otp->updated_at < now()->subMinutes(5)) {
                $fail('otp', 'Your otp already expired.');
            }

            if ($otp->updated_at < now()->subMinutes(5)) {
                $fail('otp', 'Your otp already expired.');
            }

            if ($otp->revoked) {
                $fail('otp', 'Your otp already used.');
            }
        } catch (\Exception $exception) {
            $fail('Internal server error!');
        }
    }
}
