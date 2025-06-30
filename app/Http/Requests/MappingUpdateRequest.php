<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class MappingUpdateRequest extends FormRequest
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
            'ownership' => 'required|string',
            'cabin_row' => 'required|numeric',
            'cabin_position' => 'required|numeric',
            'fare' => 'required|numeric',
            'service_charge' => 'required|numeric'
        ];
    }
}
