<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CabinTypeCreateRequest extends FormRequest
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
            'name'=>'bail|required|max:191',
            'is_ac'=>'bail|integer|nullable',
            'letter' => 'bail|required|max:5|min:1',
            'capacity' => 'bail|required|integer',
            'type' => 'bail|required|in:cabin,seat,sofa',
            'service_type' => 'bail|required|exists:services,slug'
        ];
    }
}
