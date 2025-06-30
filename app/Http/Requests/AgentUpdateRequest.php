<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AgentUpdateRequest extends FormRequest
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
            'nid_no' => 'bail|required|unique:user_metas,nid_no,' . request()->input('meta_id'),
            'nid_attachment' => 'bail|nullable|mimes:jpg,png,gif',
            'nid_photo' => 'bail|nullable',
            'trade_license' => 'bail|nullable|unique:user_metas,trade_license,' . request()->input('meta_id'),
            'trade_attachment' => 'bail|nullable|mimes:jpg,png,gif',
            'trade_photo' => 'bail|nullable',
            'meta_id' => 'bail|required|numeric',
            'incentive_id' => 'bail|required|numeric'
        ];
    }
}
