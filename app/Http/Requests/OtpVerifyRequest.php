<?php

namespace App\Http\Requests;

use App\Traits\FormValidationResponseTrait;
use Illuminate\Foundation\Http\FormRequest;

class OtpVerifyRequest extends FormRequest
{
    use FormValidationResponseTrait;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'mobile' => 'bail|required|max:14|regex:/^(01){1}[3456789]{1}(\d){8}$/|min:11',
            'otp' => 'bail|nullable|max:6|exists:user_otps,otp_code',
            'type' => 'nullable'
        ];
    }
}
