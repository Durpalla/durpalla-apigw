<?php

namespace App\Http\Requests\Merchant;

use Illuminate\Foundation\Http\FormRequest;

class MerchantVerifyResetOtpRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'token' => ['required', 'string'],
            'code' => ['required', 'string', 'size:6'],
        ];
    }

    public function authorize(): bool
    {
        return true;
    }
}
