<?php

namespace Modules\Auth\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Modules\Auth\Rules\UserForgotPasswordRule;

class ResetEmailRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'email' => ['required', 'email', 'exists:users,email', new UserForgotPasswordRule()],
        ];
    }

    public function authorize(): bool
    {
        return true;
    }
}
