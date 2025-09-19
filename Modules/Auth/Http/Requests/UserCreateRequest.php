<?php

namespace Modules\Auth\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Modules\Auth\Rules\StrongPasswordRule;

class UserCreateRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'name' => 'bail|required|string',
            'email' => 'bail|required|email|unique:users,email',
            'role' => 'bail|required|exists:roles,id',
            'password' => ['bail', 'required', 'min:6', 'required_with:password_confirm', new StrongPasswordRule()],
            'password_confirm' => 'bail|required|same:password',
            'status' => 'required|integer|in:1,0'
        ];
    }

    public function authorize(): bool
    {
        return true;
    }
}
