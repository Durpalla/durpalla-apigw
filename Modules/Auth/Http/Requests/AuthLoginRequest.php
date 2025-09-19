<?php

namespace Modules\Auth\Http\Requests;

use App\Traits\FormValidationResponseTrait;
use Illuminate\Foundation\Http\FormRequest;
use Modules\Auth\Rules\UserLoginRule;

class AuthLoginRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'email' => ['required', 'email', 'exists:users,email', new UserLoginRule()],
            'password' => ['required', 'string'],
            'g-recaptcha-response' => 'required|captcha'
        ];
    }

    public function authorize(): bool
    {
        return true;
    }
}
