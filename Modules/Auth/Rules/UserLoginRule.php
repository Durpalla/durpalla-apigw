<?php

namespace Modules\Auth\Rules;

use App\Helpers\LogHelper;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Facades\Hash;
use Modules\Auth\Constants\AuthConstant;
use Modules\Auth\Entities\User;
use Modules\Auth\Traits\AccessBlockingTrait;
use Modules\Auth\Traits\LoginTrait;

class UserLoginRule implements ValidationRule
{
    use LoginTrait, AccessBlockingTrait;

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $request = request();
        try {
            if($this->hasAlreadyBlocked($value)) {
                $fail('email', 'Your account has been blocked due to multiple failed attempts.');
                return;
            }

            $user = User::where('email', $value)->first();
            if(!$user) {
                $this->failedAttempt($request, $value);
                $fail(__('Account not found'));
                return;
            }

            if($user->status != AuthConstant::USER_ACTIVE) {
                $this->failedAttempt($request, $value);
                $fail(__('Your account is not active'));
                return;
            }

            if(!Hash::check(request()->input('password'), $user->password)) {
                $this->failedAttempt($request, $value);
                $fail('password', __('The password does not match our records.'));
                return;
            }
        } catch (\Exception $exception) {
            $this->failedAttempt($request, $value);
            $fail('email', 'Sorry! something went wrong.');
            LogHelper::exception($exception, [
                'keyword' => 'USER_LOGIN_CHECK_EXCEPTION',
            ]);
        }
    }
}
