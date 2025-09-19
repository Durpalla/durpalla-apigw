<?php

namespace Modules\Auth\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Modules\Auth\Rules\StrongPasswordRule;

class ResetPasswordRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'token' => 'required',
            'password' => ['bail', 'required', 'min:6', 'required', new StrongPasswordRule()],
            'password_confirmation' => ['bail', 'required', 'same:password'],
            'g-recaptcha-response' => 'required|captcha'
        ];
    }

    public function authorize(): bool
    {
        return true;
    }
}
