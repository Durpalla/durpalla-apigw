<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Customer login: mobile + password or PIN.
 */
class CustomerLoginRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'mobile' => ['required', 'string', 'max:20'],
            'password' => ['required_without:pin', 'nullable', 'string'],
            'pin' => ['required_without:password', 'nullable', 'string', 'max:20'],
        ];
    }

    public function authorize(): bool
    {
        return true;
    }
}
