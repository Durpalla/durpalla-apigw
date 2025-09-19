<?php

namespace Modules\Auth\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Modules\Auth\Rules\StrongPasswordRule;

class UserUpdateRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'name' => 'bail|required|string',
            'email' => 'bail|required|email|unique:users,email,' . $this->user,
            'role' => 'bail|required|exists:roles,id',
            'password' => ['bail', 'nullable', 'min:6', 'same:password_confirm', new StrongPasswordRule()],
            'status' => 'required|integer|in:1,9'
        ];
    }

    public function authorize(): bool
    {
        return auth()->check() && $this->user > 1 && auth()->id() != $this->user;
    }
}
