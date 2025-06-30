<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class PartnerCreateRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize(): bool
    {
        return auth()->check();
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules(): array
    {
        return [
            'name' => 'bail|required|string',
            'email' => 'bail|required|email|unique:users,email',
            'mobile' => 'bail|required|unique:users,mobile',
            'address' => 'bail|nullable|string',
            'password' => 'bail|required|string|same:password_confirm',
            'type' => 'bail|required|in:partner',
            'incentive' => 'bail|required|numeric',
            'incentive_type' => 'bail|required|in:percent,fixed'
        ];
    }
}
