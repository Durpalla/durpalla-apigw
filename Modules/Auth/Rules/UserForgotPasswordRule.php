<?php

namespace Modules\Auth\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Str;
use Modules\Auth\Constants\AuthConstant;
use Modules\Auth\Entities\User;
use Modules\Auth\Traits\AccessBlockingTrait;
use Modules\Auth\Traits\LoginTrait;

class UserForgotPasswordRule implements ValidationRule
{
    use LoginTrait, AccessBlockingTrait;

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $request = request();
        try {
            if($this->hasAlreadyBlocked($value)) {
                $fail('Sorry! Your account has blocked due to too many failed attempts.');
                return;
            }

            $user = User::where('email', $value)->first();
            if (!$user) {
                $this->failedAttempt($request, $value);
                $fail('Email is not registered.');
                return;
            }

            if ($user->status != AuthConstant::USER_ACTIVE) {
                $this->failedAttempt($request, $value);
                $fail('The account is not active with this email address.');
                return;
            }
        } catch (\Exception $e) {
            $this->failedAttempt($request, $value);
            $fail($e->getMessage());
        }
    }
}
