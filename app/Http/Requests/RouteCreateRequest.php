<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class RouteCreateRequest extends FormRequest
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
            'route_name'=>'bail|required|string|max:191',
            'route_no' => 'bail|required|integer|unique:vehicle_routes,route_no',
            'route_type'=>'bail|nullable|max:191|string',
            'property_name' => 'bail|required|array',
            'property_type' => 'bail|required|array',
            'property_position' => 'bail|required|array'
        ];
    }
}
