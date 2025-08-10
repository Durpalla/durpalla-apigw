<?php

namespace App\Http\Requests;

use App\Rules\GhatUniqueRule;
use Illuminate\Foundation\Http\FormRequest;

class GhatUpdateRequest extends FormRequest
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
            'name' => [
                'bail',
                'required',
                'string',
                new GhatUniqueRule($this->id)
            ],
            'latitude' => 'bail|nullable',
            'longitude' => 'bail|nullable',
            'altitude' => 'bail|nullable',
            'service_type' => 'bail|required|exists:services,slug'
        ];
    }
}
