<?php

namespace App\Rules;

use App\Constants\AppConst;
use App\Helpers\LogHelper;
use App\Models\User;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class AuthCheckRule implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        try {
            $user = User::where('mobile', $value)->first();
            if($user && $user->type != AppConst::USER_TYPE_CUSTOMER) {
                $fail('mobile', __('Sorry! this mobile number is locked!'));
                return;
            }
        } catch (\Exception $exception) {
            LogHelper::exception($exception, [
                'keyword' => 'AUTH_CHECK_EXCEPTION'
            ]);
            $fail('mobile', __('Internal server error!'));
        }
    }
}
