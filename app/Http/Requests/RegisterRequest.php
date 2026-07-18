<?php

namespace App\Http\Requests;

use App\Traits\FormValidationResponseTrait;
use Illuminate\Foundation\Http\FormRequest;

class RegisterRequest extends FormRequest
{
    use FormValidationResponseTrait;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => 'bail|required|max:191|min:3',
            'email' => 'bail|required|max:191|email|unique:customers,email',
            'mobile' => 'bail|required|max:14|regex:/^(01){1}[3456789]{1}(\d){8}$/|min:11|unique:customers,mobile',
            'nid' => 'bail|nullable|min:10|max:17|string',
            'password' => 'bail|required|min:8|max:20',
            'confirm_password' => 'bail|required|min:8|max:20|same:password',
            'platform' => 'bail|nullable',
            'device_id' => 'bail|nullable|string',
        ];
    }
}
