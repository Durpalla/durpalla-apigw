<?php

namespace Modules\Auth\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Modules\Auth\Rules\OldPasswordRule;
use Modules\Auth\Rules\StrongPasswordRule;

class ChangePasswordRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'email' => 'required|email|exists:users,email',
            'old_password' => ['required', new OldPasswordRule()],
            'password' => ['bail', 'required', 'min:6', 'required', 'same:password_confirm', new StrongPasswordRule()],
        ];
    }

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }
}
