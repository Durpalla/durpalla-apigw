<?php

namespace App\Http\Requests\Merchant;

use Illuminate\Foundation\Http\FormRequest;

class MerchantResetPasswordRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'token' => ['required', 'string'],
            'password' => ['required', 'string', 'min:8', 'max:191'],
            'password_confirmation' => ['required', 'same:password'],
        ];
    }

    public function authorize(): bool
    {
        return true;
    }
}
