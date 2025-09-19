<?php

namespace Modules\Auth\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Facades\Hash;

class OldPasswordRule implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        try {
            $user = auth()->user();
            if(!Hash::check($value, $user->password)) {
                $fail('Old password does not match.');
                return;
            }
        } catch (\Exception $exception) {
            $fail($exception->getMessage());
        }
    }
}
