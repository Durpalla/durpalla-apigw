<?php

namespace Modules\Auth\Http\Requests;

use App\Traits\FormValidationResponseTrait;
use Illuminate\Foundation\Http\FormRequest;

class LoginRequest extends FormRequest
{
    use FormValidationResponseTrait;

    public function rules(): array
    {
        return [
            'email' => ['required', 'email', 'exists:vendors,email'],
            'password' => ['required', 'string'],
            'g-recaptcha-response' => 'required|captcha'
        ];
    }

    public function authorize(): bool
    {
        return true;
    }
}
