<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AgentCreateRequest extends FormRequest
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
            'nid_no' => 'bail|required|unique:user_metas,nid_no',
            'nid_photo' => 'bail|nullable',
            'type' => 'bail|required|in:agent',
            'nid_attachment' => 'bail|required|mimes:jpg,png,gif',
            'trade_license' => 'bail|nullable|unique:user_metas,trade_license',
            'trade_attachment' => 'bail|nullable|mimes:jpg,png,gif',
            'trade_license_photo' => 'bail|nullable',
            'incentive' => 'bail|required|numeric',
            'incentive_type' => 'bail|required|in:percent,fixed'
        ];
    }
}
