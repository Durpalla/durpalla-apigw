<?php

namespace App\Http\Requests;

use App\Rules\AuthCheckRule;
use App\Traits\FormValidationResponseTrait;
use Illuminate\Foundation\Http\FormRequest;

class LoginRequest extends FormRequest
{
    use FormValidationResponseTrait;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'password' => 'bail|required|min:8|max:20',
            'device_id' => 'bail|string|max:191',
            'mobile' => [
                'bail',
                'required',
                'max:14',
                'regex:/^(01){1}[3456789]{1}(\d){8}$/',
                'min:11',
                new AuthCheckRule()
            ]
        ];
    }
}
