<?php

namespace Modules\Auth\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Str;

class StrongPasswordRule implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        try {
            if((Str::lower($value) === $value)) {
                $fail('The :attribute must contain at least one uppercase character.');
                return;
            }

            if((Str::upper($value) === $value)) {
                $fail('The :attribute must contain at least one lowercase character.');
                return;
            }

            if(!preg_match('/[0-9]/', $value)) {
                $fail('The :attribute must contain at least one number.');
                return;
            }

            if(!(preg_match('/[^A-Za-z0-9]/', $value))) {
                $fail('The :attribute must contain at least one special character.');
                return;
            }

        } catch (\Throwable $th) {
            $fail('Sorry! Could not validate password policy');
        }
    }
}
