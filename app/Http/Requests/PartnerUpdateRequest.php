<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class PartnerUpdateRequest extends FormRequest
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
            'email' => 'bail|required|email|unique:users,email,' . $this->agent,
            'mobile' => 'bail|required|unique:users,mobile,' . $this->agent,
            'address' => 'bail|nullable|string',
            'password' => 'bail|nullable|string|same:password_confirm',
            'meta_id' => 'bail|required|numeric',
            'incentive_id' => 'bail|required|numeric'
        ];
    }
}
