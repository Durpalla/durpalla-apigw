<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UserCreateRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        return [
            'name' => 'bail|required|string|max:191',
            'email' => 'bail|required|unique:users,email',
            // 'username' => 'bail|nullable|unique:users,username',
            'mobile' => 'bail|required|regex:/^(01){1}[3456789]{1}(\d){8}$/|unique:users,mobile',
            'password' => 'bail|required|same:password_confirm|min:8|max:32',
            'designation_id' => 'bail|required|numeric|exists:designations,id',
            'password_confirm' => 'required',
            'role' => 'bail|required|integer',
            'avatar' => 'bail|nullable|image|mimes:jpeg,png,jpg,gif,svg|max:2048|dimensions:min_width=100,min_height=100',
            'counter_id' => 'bail|nullable|numeric|exists:ghats,id'
        ];
    }
}
