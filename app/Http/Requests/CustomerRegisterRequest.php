<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CustomerRegisterRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'unique:customers,email'],
            'mobile' => ['nullable', 'string', 'max:20', 'unique:customers,mobile'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ];
    }

    public function authorize(): bool
    {
        return true;
    }
}
