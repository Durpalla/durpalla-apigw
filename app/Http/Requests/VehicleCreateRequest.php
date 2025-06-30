<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class VehicleCreateRequest extends FormRequest
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
            'merchant_id' => 'bail|required|integer|exists:users,id',
            'route_id' => 'bail|required|integer|exists:vehicle_routes,id',
            'name' => 'bail|required|string|max:191|unique:vehicles,name',
            'registration_no' => 'bail|required|string|unique:vehicles,registration_no',
            'registration_expiry_date' => 'bail|nullable|max:191|string',
            'fitness_expiry_date' => 'bail|nullable',
            'passengers_capacity' => 'bail|nullable|integer',
            'vehicle_type' => 'bail|required|exists:services,slug',
            'nid_verification_check' => 'bail|required|numeric',
            'number_of_floor' => 'bail|required|numeric',
            'ac_available' => 'bail|required|numeric|in:0,1',
            'default_tab' => 'bail|required|in:cabin,seat,deck',
            'default_floor' => 'bail|required|numeric'
        ];
    }
}
