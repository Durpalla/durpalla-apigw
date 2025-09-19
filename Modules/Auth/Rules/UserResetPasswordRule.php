<?php

namespace Modules\Auth\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Str;
use Modules\Auth\Constants\AuthConstant;
use Modules\Auth\Entities\Otp;
use Modules\Auth\Traits\Auth2FaTrait;

class UserResetPasswordRule implements ValidationRule
{
    use Auth2FaTrait;

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        try {
            $request = request();
            if($request->isMethod('POST')) {
                $token = $request->get('token');
                $otp = Otp::where('created_at', '>=', now()->subMinutes(5))->where('email', $this->decryptToken($token))->first();
                if(!$otp) {
                    $fail('Sorry! the otp is not valid or has expired.');
                    return;
                }

                if(!$otp->user) {
                    $fail('Sorry! could not verify otp of user');
                    return;
                }

                if($otp->user->status != AuthConstant::USER_ACTIVE) {
                    $fail('Sorry! the user is not active.');
                    return;
                }
            }
        } catch (\Exception $exception) {
            $fail($exception->getMessage());
        }
    }
}
