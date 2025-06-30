<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class PartyCreateRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        return auth()->check();
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
            'slug' => 'bail|required|string|max:25|unique:parties,slug',
            'email' => 'bail|email|required|max:191,unique:users,email',
            'mobile' => 'bail|required|regex:/^(01){1}[3456789]{1}(\d){8}$/|unique:users,mobile',
            'services' => 'bail|required|array'
        ];
    }
}
